<?php

namespace App\Services;

use App\Enums\CashFlowRowKind;
use App\Enums\MovementKind;
use App\Enums\MovementSource;
use App\Models\CashFlowPlan;
use App\Models\CashFlowRow;
use App\Models\Movement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gère les plans de trésorerie annuels : auto-création et sauvegarde
 * batch de la grille.
 *
 * Une grille = un Plan annuel → N Rows → 12 Movements par Row
 * (une par mois, estimated_on = 15 du mois).
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
     * Sauvegarde batch : wipe + recreate Rows et leurs Movements.
     * Les Movements ponctuelles (cash_flow_row_id = null) du user
     * ne sont pas touchées.
     *
     * @param  array{
     *   startingBalance?: float|int,
     *   irpfExempt?: bool,
     *   incomes?: array<int, array{label:string,clientName?:string|null,categoryId?:int|null,hasIva?:bool,hasIrpf?:bool,monthly:array<int,float>,paid:array<int,bool>}>,
     *   expenses?: array<int, array{label:string,categoryId?:int|null,hasIva?:bool,monthly:array<int,float>,paid:array<int,bool>}>,
     *   salary?: array{label?:string,monthly:array<int,float>,paid:array<int,bool>},
     *   quarterlyTaxes?: array{ivaPaid?:array<int,bool>,irpfPaid?:array<int,bool>}
     * }  $payload
     */
    public function save(CashFlowPlan $plan, array $payload): void
    {
        DB::transaction(function () use ($plan, $payload) {
            $plan->update([
                'starting_balance' => $this->toCents($payload['startingBalance'] ?? 0),
                'irpf_exempt' => (bool) ($payload['irpfExempt'] ?? false),
            ]);

            // Wipe : Rows + leurs Movements (cascade via FK).
            $plan->rows()->delete();
            // Wipe : Movements kind=tax sans row (les taxes payées).
            $plan->movements()
                ->where('kind', MovementKind::Tax->value)
                ->whereNull('cash_flow_row_id')
                ->delete();

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

            $this->upsertTaxMovements($plan, $payload['quarterlyTaxes'] ?? []);
        });
    }

    /**
     * @param  array{label?:string,clientName?:string|null,categoryId?:int|null,hasIva?:bool,hasIrpf?:bool,monthly:array<int,float>,paid?:array<int,bool>}  $row
     */
    private function createRow(CashFlowPlan $plan, CashFlowRowKind $kind, array $row, int $sortOrder): void
    {
        $created = CashFlowRow::create([
            'plan_id' => $plan->id,
            'kind' => $kind,
            'label' => $row['label'] ?? ($kind === CashFlowRowKind::Salary ? 'Salario' : 'Sin nombre'),
            'client_name' => $row['clientName'] ?? null,
            'category_id' => $row['categoryId'] ?? null,
            'has_iva' => $kind === CashFlowRowKind::Salary ? false : ($row['hasIva'] ?? true),
            'has_irpf' => $kind === CashFlowRowKind::Income ? ($row['hasIrpf'] ?? true) : false,
            'sort_order' => $sortOrder,
        ]);

        $movementKind = match ($kind) {
            CashFlowRowKind::Income => MovementKind::Income,
            CashFlowRowKind::Expense => MovementKind::Expense,
            CashFlowRowKind::Salary => MovementKind::Salary,
        };

        $monthly = $row['monthly'] ?? [];
        $paid = $row['paid'] ?? [];

        foreach (range(0, 11) as $i) {
            $amount = $this->toCents($monthly[$i] ?? 0);
            if ($amount === 0) {
                continue; // pas de movement pour les cellules vides
            }
            Movement::create([
                'user_id' => $plan->user_id,
                'cash_flow_plan_id' => $plan->id,
                'cash_flow_row_id' => $created->id,
                'category_id' => $row['categoryId'] ?? null,
                'kind' => $movementKind,
                'label' => $created->label,
                'client_name' => $row['clientName'] ?? null,
                'amount' => $amount,
                'estimated_on' => CarbonImmutable::create($plan->year, $i + 1, 15)->toDateString(),
                'paid_at' => ($paid[$i] ?? false) ? now() : null,
                'iva_rate' => null,
                'irpf_rate' => null,
                'has_iva' => $created->has_iva,
                'has_irpf' => $created->has_irpf,
                'source' => MovementSource::Recurring,
            ]);
        }
    }

    /**
     * Les taxes trimestrielles payées sont matérialisées en Movements
     * kind=tax. Le montant exact est dérivé côté UI ; on stocke 0 ici
     * (le label suffit à les identifier ; le montant sera mis à jour
     * par un futur calcul si besoin). Pour le MVP : seul l'état "payé"
     * compte côté DB, le montant est recalculé live.
     *
     * @param  array{ivaPaid?:array<int,bool>,irpfPaid?:array<int,bool>}  $taxes
     */
    private function upsertTaxMovements(CashFlowPlan $plan, array $taxes): void
    {
        $map = [
            'iva' => $taxes['ivaPaid'] ?? [],
            'irpf' => $taxes['irpfPaid'] ?? [],
        ];

        foreach ($map as $kindLabel => $paidArr) {
            foreach ($paidArr as $idx => $paid) {
                if (! $paid) {
                    continue;
                }
                $quarter = $idx + 1;
                Movement::create([
                    'user_id' => $plan->user_id,
                    'cash_flow_plan_id' => $plan->id,
                    'cash_flow_row_id' => null,
                    'category_id' => null,
                    'kind' => MovementKind::Tax,
                    'label' => strtoupper($kindLabel)." Q{$quarter} {$plan->year}",
                    'client_name' => null,
                    'amount' => 0, // calculé live côté Vue
                    'estimated_on' => $this->modeloDeadline($plan->year, $quarter)->toDateString(),
                    'paid_at' => now(),
                    'iva_rate' => null,
                    'irpf_rate' => null,
                    'has_iva' => false,
                    'has_irpf' => false,
                    'source' => MovementSource::Manual,
                ]);
            }
        }
    }

    private function modeloDeadline(int $year, int $quarter): CarbonImmutable
    {
        return match ($quarter) {
            1 => CarbonImmutable::create($year, 4, 20),
            2 => CarbonImmutable::create($year, 7, 20),
            3 => CarbonImmutable::create($year, 10, 20),
            4 => CarbonImmutable::create($year + 1, 1, 30),
            default => CarbonImmutable::create($year, 12, 31),
        };
    }

    private function seedDefaults(User $user, CashFlowPlan $plan): void
    {
        $profile = $user->financialProfile;
        $monthlySalary = $profile?->monthly_salary ?? 0;

        if ($monthlySalary <= 0) {
            return;
        }

        $row = CashFlowRow::create([
            'plan_id' => $plan->id,
            'kind' => CashFlowRowKind::Salary,
            'label' => 'Salario',
            'has_iva' => false,
            'has_irpf' => false,
            'sort_order' => 0,
        ]);

        foreach (range(1, 12) as $month) {
            Movement::create([
                'user_id' => $user->id,
                'cash_flow_plan_id' => $plan->id,
                'cash_flow_row_id' => $row->id,
                'kind' => MovementKind::Salary,
                'label' => 'Salario',
                'amount' => $monthlySalary,
                'estimated_on' => CarbonImmutable::create($plan->year, $month, 15)->toDateString(),
                'paid_at' => null,
                'has_iva' => false,
                'has_irpf' => false,
                'source' => MovementSource::Recurring,
            ]);
        }
    }

    private function toCents(float|int $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
