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
 * Gère les plans de trésorerie annuels.
 *
 * Le Cashflow est une vue de PROJECTION : il édite les amounts mensuels
 * des Movements rattachées aux Rows. Le statut payé (paid_at) n'est PAS
 * géré ici — il se gère uniquement via /movements.
 *
 * Conséquence importante : à chaque save, on upsert (sans wipe) pour
 * préserver le paid_at déjà existant sur les Movements concernées.
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
     * Upsert de la grille. On NE WIPE PAS — on garde les paid_at existants.
     *
     * @param  array{
     *   startingBalance?: float|int,
     *   irpfExempt?: bool,
     *   incomes?: array<int, array{id?:int|null,label:string,clientName?:string|null,categoryId?:int|null,hasIva?:bool,hasIrpf?:bool,monthly:array<int,float>}>,
     *   expenses?: array<int, array{id?:int|null,label:string,categoryId?:int|null,hasIva?:bool,monthly:array<int,float>}>,
     *   salary?: array{id?:int|null,label?:string,monthly:array<int,float>}
     * }  $payload
     */
    public function save(CashFlowPlan $plan, array $payload): void
    {
        DB::transaction(function () use ($plan, $payload) {
            $plan->update([
                'starting_balance' => $this->toCents($payload['startingBalance'] ?? 0),
                'irpf_exempt' => (bool) ($payload['irpfExempt'] ?? false),
            ]);

            $touchedRowIds = [];
            $sort = 0;

            foreach ($payload['incomes'] ?? [] as $row) {
                $touchedRowIds[] = $this->upsertRow($plan, CashFlowRowKind::Income, $row, $sort++);
            }
            foreach ($payload['expenses'] ?? [] as $row) {
                $touchedRowIds[] = $this->upsertRow($plan, CashFlowRowKind::Expense, $row, $sort++);
            }
            if (isset($payload['salary'])) {
                $touchedRowIds[] = $this->upsertRow($plan, CashFlowRowKind::Salary, $payload['salary'], $sort++);
            }

            // Rows présentes en DB mais absentes du payload → supprimées
            // (cascade détruit leurs Movements).
            $plan->rows()->whereNotIn('id', array_filter($touchedRowIds))->delete();
        });
    }

    /**
     * Upsert d'une row + ses 12 movements (un par mois).
     * Renvoie l'id de la row. Préserve paid_at des Movements existantes.
     */
    private function upsertRow(CashFlowPlan $plan, CashFlowRowKind $kind, array $rowData, int $sortOrder): int
    {
        $rowAttrs = [
            'kind' => $kind,
            'label' => $rowData['label'] ?? ($kind === CashFlowRowKind::Salary ? 'Salario' : 'Sin nombre'),
            'client_name' => $rowData['clientName'] ?? null,
            'category_id' => $rowData['categoryId'] ?? null,
            'has_iva' => $kind === CashFlowRowKind::Salary ? false : ($rowData['hasIva'] ?? true),
            'has_irpf' => $kind === CashFlowRowKind::Income ? ($rowData['hasIrpf'] ?? true) : false,
            'sort_order' => $sortOrder,
        ];

        $existingId = $rowData['id'] ?? null;
        $row = $existingId
            ? $plan->rows()->where('id', $existingId)->first()
            : null;

        if ($row) {
            $row->update($rowAttrs);
        } else {
            $row = CashFlowRow::create(['plan_id' => $plan->id, ...$rowAttrs]);
        }

        $movementKind = match ($kind) {
            CashFlowRowKind::Income => MovementKind::Income,
            CashFlowRowKind::Expense => MovementKind::Expense,
            CashFlowRowKind::Salary => MovementKind::Salary,
        };

        $existingMovements = $row->movements()
            ->get()
            ->keyBy(fn (Movement $m) => $m->estimated_on->month);

        foreach (range(1, 12) as $month) {
            $amount = $this->toCents($rowData['monthly'][$month - 1] ?? 0);
            $existing = $existingMovements->get($month);

            if ($amount === 0) {
                // Cellule vide → supprimer la Movement existante (paid_at perdu si présent).
                $existing?->delete();

                continue;
            }

            if ($existing) {
                // Met à jour l'amount + meta, garde paid_at intact.
                $existing->update([
                    'amount' => $amount,
                    'category_id' => $row->category_id,
                    'label' => $row->label,
                    'client_name' => $row->client_name,
                    'has_iva' => $row->has_iva,
                    'has_irpf' => $row->has_irpf,
                ]);
            } else {
                $estimatedOn = CarbonImmutable::create($plan->year, $month, 15)->toDateString();
                Movement::create([
                    'user_id' => $plan->user_id,
                    'cash_flow_plan_id' => $plan->id,
                    'cash_flow_row_id' => $row->id,
                    'category_id' => $row->category_id,
                    'kind' => $movementKind,
                    'label' => $row->label,
                    'client_name' => $row->client_name,
                    'amount' => $amount,
                    'issued_on' => $movementKind === MovementKind::Income ? $estimatedOn : null,
                    'estimated_on' => $estimatedOn,
                    'paid_at' => null,
                    'has_iva' => $row->has_iva,
                    'has_irpf' => $row->has_irpf,
                    'source' => MovementSource::Recurring,
                ]);
            }
        }

        return $row->id;
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
