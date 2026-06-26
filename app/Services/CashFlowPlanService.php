<?php

namespace App\Services;

use App\Enums\CashFlowRowKind;
use App\Enums\QuarterlyTaxKind;
use App\Models\CashFlowCell;
use App\Models\CashFlowPlan;
use App\Models\CashFlowRow;
use App\Models\QuarterlyTax;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gère les plans de trésorerie annuels : création par défaut
 * et sauvegarde batch de toute la grille.
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
            }

            return $plan;
        });
    }

    /**
     * @param  array{
     *   startingBalance?: float|int,
     *   irpfExempt?: bool,
     *   incomes?: array<int, array{id?:int,label:string,clientName?:string|null,categoryId?:int|null,monthly:array<int,float>,paid:array<int,bool>}>,
     *   expenses?: array<int, array{id?:int,label:string,categoryId?:int|null,monthly:array<int,float>,paid:array<int,bool>}>,
     *   salary?: array{id?:int|null,label?:string,monthly:array<int,float>,paid:array<int,bool>},
     *   quarterlyTaxes?: array{iva:array<int,float>,irpf:array<int,float>}
     * }  $payload
     */
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

            // Quarterly taxes : wipe + re-insert.
            $plan->quarterlyTaxes()->delete();
            foreach ([QuarterlyTaxKind::Iva, QuarterlyTaxKind::Irpf] as $kind) {
                $key = $kind->value;
                $values = $payload['quarterlyTaxes'][$key] ?? [];
                foreach ($values as $idx => $amount) {
                    QuarterlyTax::create([
                        'plan_id' => $plan->id,
                        'kind' => $kind,
                        'quarter' => $idx + 1,
                        'amount' => $this->toCents($amount),
                    ]);
                }
            }
        });
    }

    /**
     * @param  array{label?:string,clientName?:string|null,categoryId?:int|null,monthly:array<int,float>,paid?:array<int,bool>}  $row
     */
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

        // Projette les charges récurrentes actives (chacune = une ligne dépense).
        $sort = 1;
        foreach ($user->recurringCharges()->active()->get() as $charge) {
            $row = CashFlowRow::create([
                'plan_id' => $plan->id,
                'kind' => CashFlowRowKind::Expense,
                'label' => $charge->label,
                'category_id' => $charge->category_id,
                'has_iva' => true,
                'sort_order' => $sort++,
            ]);

            // Approxime : pour mensuel, applique amount chaque mois.
            // Pour quarterly, applique sur les mois 1,4,7,10. Pour yearly, sur mois 1.
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
