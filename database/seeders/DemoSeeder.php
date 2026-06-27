<?php

namespace Database\Seeders;

use App\Enums\CashFlowRowKind;
use App\Enums\FiscalRegime;
use App\Enums\MovementKind;
use App\Enums\MovementSource;
use App\Models\CashFlowRow;
use App\Models\FinancialProfile;
use App\Models\Movement;
use App\Models\User;
use App\Support\CreateDefaultCategories;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::today();

        $user = User::firstOrCreate(
            ['email' => 'demo@fitnances.app'],
            [
                'name' => 'Nicolas del Castillo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        FinancialProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => 'Nicolas del Castillo',
                'nif' => 'Y1234567X',
                'activity' => 'Desarrollo de software',
                'province' => 'Barcelona',
                'regime' => FiscalRegime::DirectSimplified,
                'iva_default' => 21,
                'irpf_default' => 15,
                'cuota_monthly' => 30000,
                'monthly_salary' => 0,
                'surcharge_equivalence' => false,
                'intra_community' => true,
                'onboarded_at' => $today,
                'currency' => 'EUR',
            ],
        );

        app(CreateDefaultCategories::class)->for($user);

        $servicios = $user->categories()->where('name', 'Servicios profesionales')->firstOrFail();
        $alquiler = $user->categories()->where('name', 'Alquiler oficina')->firstOrFail();
        $cuotaCat = $user->categories()->where('name', 'Cuota autónomos')->firstOrFail();

        $plan = $user->cashFlowPlans()->firstOrCreate(
            ['year' => $today->year],
            ['starting_balance' => 0, 'irpf_exempt' => false],
        );

        $paidMonths = range(1, max(0, $today->month - 1));

        // 1. Ingreso : 3 000 €/mois avec IVA + IRPF.
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Income, 'Cliente recurrente',
            categoryId: $servicios->id,
            clientName: 'Cliente recurrente',
            amountCents: 300000,
            hasIva: true,
            hasIrpf: true,
            paidMonths: $paidMonths,
        );

        // 2. Coworking : 150 €/mois avec IVA + IRPF.
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Expense, 'Coworking',
            categoryId: $alquiler->id,
            amountCents: 15000,
            hasIva: true,
            hasIrpf: true,
            paidMonths: $paidMonths,
        );

        // 3. Cuota autónomos : 300 €/mois, sans IVA ni IRPF.
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Expense, 'Cuota autónomos',
            categoryId: $cuotaCat->id,
            amountCents: 30000,
            hasIva: false,
            hasIrpf: false,
            paidMonths: $paidMonths,
        );
    }

    /**
     * Crée une row + 12 movements (un par mois), avec paid_at sur les mois passés.
     *
     * @param  array<int>  $paidMonths  1-based month numbers to mark paid
     */
    private function createRecurringRow(
        \App\Models\CashFlowPlan $plan,
        CashFlowRowKind $rowKind,
        string $label,
        int $amountCents,
        ?int $categoryId = null,
        ?string $clientName = null,
        bool $hasIva = true,
        bool $hasIrpf = false,
        array $paidMonths = [],
    ): void {
        $row = CashFlowRow::create([
            'plan_id' => $plan->id,
            'kind' => $rowKind,
            'label' => $label,
            'client_name' => $clientName,
            'category_id' => $categoryId,
            'has_iva' => $rowKind === CashFlowRowKind::Salary ? false : $hasIva,
            'has_irpf' => $rowKind === CashFlowRowKind::Salary ? false : $hasIrpf,
            'sort_order' => 0,
        ]);

        $movementKind = match ($rowKind) {
            CashFlowRowKind::Income => MovementKind::Income,
            CashFlowRowKind::Expense => MovementKind::Expense,
            CashFlowRowKind::Salary => MovementKind::Salary,
        };

        foreach (range(1, 12) as $month) {
            $estimatedOn = CarbonImmutable::create($plan->year, $month, 15);
            // Pour les income, on fait comme si la facture avait été émise
            // ~10 jours avant la date prévue d'encaissement (cobro typique à 10j).
            $issuedOn = $movementKind === MovementKind::Income
                ? $estimatedOn->subDays(10)
                : null;
            // Paiement effectif : pour les mois passés on simule un cobro
            // dans les jours qui suivent estimated_on (DSO ≈ 12 jours pour
            // les income, paiement le jour même pour expense/salary).
            $paidAt = null;
            if (in_array($month, $paidMonths, true)) {
                $paidAt = $movementKind === MovementKind::Income
                    ? $estimatedOn->addDays(2)
                    : $estimatedOn;
            }
            Movement::create([
                'user_id' => $plan->user_id,
                'cash_flow_plan_id' => $plan->id,
                'cash_flow_row_id' => $row->id,
                'category_id' => $categoryId,
                'kind' => $movementKind,
                'label' => $label,
                'client_name' => $clientName,
                'amount' => $amountCents,
                'issued_on' => $issuedOn?->toDateString(),
                'estimated_on' => $estimatedOn->toDateString(),
                'paid_at' => $paidAt,
                'has_iva' => $row->has_iva,
                'has_irpf' => $row->has_irpf,
                'source' => MovementSource::Recurring,
            ]);
        }
    }
}
