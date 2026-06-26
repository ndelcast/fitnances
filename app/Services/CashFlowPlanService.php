<?php

namespace App\Services;

use App\Enums\CashFlowRowKind;
use App\Enums\QuarterlyTaxKind;
use App\Enums\TransactionType;
use App\Models\CashFlowCell;
use App\Models\CashFlowPlan;
use App\Models\CashFlowRow;
use App\Models\FinancialProfile;
use App\Models\QuarterlyTax;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gère les plans de trésorerie annuels : création par défaut,
 * sauvegarde batch de la grille et synchronisation des Transactions
 * dérivées (une transaction par cellule non nulle ingreso/gasto).
 */
final class CashFlowPlanService
{
    public function forYear(User $user, int $year): CashFlowPlan
    {
        return DB::transaction(function () use ($user, $year) {
            $plan = $user->cashFlowPlans()->firstOrCreate(
                ['year' => $year],
                ['starting_balance' => 0, 'irpf_exempt' => false],
            );

            if ($plan->wasRecentlyCreated) {
                $this->seedDefaults($user, $plan);
                $this->syncTransactions($plan);
            }

            return $plan;
        });
    }

    public function save(CashFlowPlan $plan, array $payload): void
    {
        DB::transaction(function () use ($plan, $payload) {
            $plan->update([
                'starting_balance' => $this->toCents($payload['startingBalance'] ?? 0),
                'irpf_exempt' => (bool) ($payload['irpfExempt'] ?? false),
            ]);

            // Wipe and rebuild rows/cells (simplifie le sync pour le MVP).
            $plan->rows()->delete();

            $sort = 0;
            foreach ($payload['incomes'] ?? [] as $row) {
                $this->createRow($plan, CashFlowRowKind::Income, $row, $sort++);
            }
            foreach ($payload['expenses'] ?? [] as $row) {
                $this->createRow($plan, CashFlowRowKind::Expense, $row, $sort++);
            }
            if (isset($payload['salary'])) {
                $this->createRow($plan, CashFlowRowKind::Salary, $payload['salary'], $sort++);
            }

            // Le montant des taxes est calculé côté Vue ; on ne persiste ici
            // que leur statut "payé" (pour le calcul du saldo réel).
            $plan->quarterlyTaxes()->delete();
            $paidMap = [
                QuarterlyTaxKind::Iva->value => $payload['quarterlyTaxes']['ivaPaid'] ?? [],
                QuarterlyTaxKind::Irpf->value => $payload['quarterlyTaxes']['irpfPaid'] ?? [],
            ];
            foreach ([QuarterlyTaxKind::Iva, QuarterlyTaxKind::Irpf] as $kind) {
                foreach ($paidMap[$kind->value] as $idx => $paid) {
                    if (! $paid) {
                        continue;
                    }
                    QuarterlyTax::create([
                        'plan_id' => $plan->id,
                        'kind' => $kind,
                        'quarter' => $idx + 1,
                        'amount' => 0,
                        'paid_at' => now(),
                    ]);
                }
            }

            $this->syncTransactions($plan->fresh(['rows.cells']));
        });
    }

    /**
     * Recrée les Transactions liées à ce plan à partir des cellules.
     * Une cellule non vide (income/expense) = une Transaction. Le salaire
     * n'est pas matérialisé (ce n'est pas un mouvement entrant/sortant
     * vers/depuis l'État ou un tiers).
     */
    private function syncTransactions(CashFlowPlan $plan): void
    {
        Transaction::where('cash_flow_plan_id', $plan->id)->delete();

        $profile = $plan->user->financialProfile;

        foreach ($plan->rows as $row) {
            if ($row->kind === CashFlowRowKind::Salary) {
                continue;
            }

            foreach ($row->cells as $cell) {
                if ((int) $cell->amount === 0) {
                    continue;
                }

                Transaction::create([
                    'user_id' => $plan->user_id,
                    'cash_flow_plan_id' => $plan->id,
                    'category_id' => $row->category_id,
                    'type' => $row->kind === CashFlowRowKind::Income
                        ? TransactionType::Income
                        : TransactionType::Expense,
                    'amount' => $cell->amount,
                    'iva_rate' => $this->ivaRateFor($row, $profile),
                    'irpf_rate' => $this->irpfRateFor($row, $profile),
                    'label' => $row->label,
                    'occurred_on' => $cell->paid_at
                        ? $cell->paid_at->toDateString()
                        : CarbonImmutable::create($plan->year, $cell->month, 15)->toDateString(),
                    'paid_at' => $cell->paid_at,
                ]);
            }
        }
    }

    private function ivaRateFor(CashFlowRow $row, ?FinancialProfile $profile): float
    {
        if (! $row->has_iva) {
            return 0;
        }

        return $profile ? (float) $profile->iva_default : 21.0;
    }

    private function irpfRateFor(CashFlowRow $row, ?FinancialProfile $profile): ?float
    {
        if ($row->kind !== CashFlowRowKind::Income) {
            return null;
        }

        if (! $row->has_irpf) {
            return 0;
        }

        return $profile ? (float) $profile->irpf_default : 15.0;
    }

    private function createRow(CashFlowPlan $plan, CashFlowRowKind $kind, array $row, int $sortOrder): void
    {
        $created = CashFlowRow::create([
            'plan_id' => $plan->id,
            'kind' => $kind,
            'label' => $row['label'] ?? ($kind === CashFlowRowKind::Salary ? 'Salario' : 'Sin nombre'),
            'client_name' => $row['clientName'] ?? null,
            'category_id' => $row['categoryId'] ?? null,
            'has_iva' => $kind === CashFlowRowKind::Salary
                ? false
                : ($row['hasIva'] ?? true),
            'has_irpf' => $kind === CashFlowRowKind::Income
                ? ($row['hasIrpf'] ?? true)
                : false,
            'sort_order' => $sortOrder,
        ]);

        $monthly = $row['monthly'] ?? [];
        $paid = $row['paid'] ?? [];
        foreach (range(0, 11) as $i) {
            CashFlowCell::create([
                'row_id' => $created->id,
                'month' => $i + 1,
                'amount' => $this->toCents($monthly[$i] ?? 0),
                'paid_at' => ($paid[$i] ?? false) ? now() : null,
            ]);
        }
    }

    private function seedDefaults(User $user, CashFlowPlan $plan): void
    {
        $profile = $user->financialProfile;
        $monthlySalary = $profile?->monthly_salary ?? 0;

        if ($monthlySalary > 0) {
            $row = CashFlowRow::create([
                'plan_id' => $plan->id,
                'kind' => CashFlowRowKind::Salary,
                'label' => 'Salario',
                'has_iva' => false,
                'has_irpf' => false,
                'sort_order' => 0,
            ]);
            foreach (range(1, 12) as $month) {
                CashFlowCell::create([
                    'row_id' => $row->id,
                    'month' => $month,
                    'amount' => $monthlySalary,
                ]);
            }
        }

        $sort = 1;
        foreach ($user->recurringCharges()->active()->get() as $charge) {
            $row = CashFlowRow::create([
                'plan_id' => $plan->id,
                'kind' => CashFlowRowKind::Expense,
                'label' => $charge->label,
                'category_id' => $charge->category_id,
                'has_iva' => true,
                'has_irpf' => false,
                'sort_order' => $sort++,
            ]);

            foreach (range(1, 12) as $month) {
                $amount = $this->amountForMonth($charge, $month);
                CashFlowCell::create([
                    'row_id' => $row->id,
                    'month' => $month,
                    'amount' => $amount,
                ]);
            }
        }
    }

    private function amountForMonth(\App\Models\RecurringCharge $charge, int $month): int
    {
        return match ($charge->frequency->value) {
            'monthly' => $charge->amount,
            'quarterly' => in_array($month, [1, 4, 7, 10], true) ? $charge->amount : 0,
            'yearly' => $month === 1 ? $charge->amount : 0,
            default => 0,
        };
    }

    private function toCents(float|int $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
