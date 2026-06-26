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
                'cuota_monthly' => 46900,
                'monthly_salary' => 220000,
                'surcharge_equivalence' => false,
                'intra_community' => true,
                'onboarded_at' => $today,
                'currency' => 'EUR',
            ],
        );

        app(CreateDefaultCategories::class)->for($user);

        $servicios = $user->categories()->where('name', 'Servicios profesionales')->firstOrFail();
        $software = $user->categories()->where('name', 'Software / suscripciones')->firstOrFail();
        $alquiler = $user->categories()->where('name', 'Alquiler oficina')->firstOrFail();
        $cuotaCat = $user->categories()->where('name', 'Cuota autónomos')->firstOrFail();

        // Plan de l'année courante.
        $plan = $user->cashFlowPlans()->firstOrCreate(
            ['year' => $today->year],
            ['starting_balance' => 0, 'irpf_exempt' => false],
        );

        // Ingresos récurrents : Acme S.L. 3 200 €/mois.
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Income, 'Factura Acme S.L.',
            categoryId: $servicios->id,
            clientName: 'Acme S.L.',
            amountCents: 320000,
            hasIva: true,
            hasIrpf: true,
            paidMonths: range(1, $today->month - 1), // les mois passés sont cobrados
        );

        // Gastos récurrents.
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Expense, 'Alquiler oficina',
            categoryId: $alquiler->id,
            amountCents: 65000,
            hasIva: true,
            paidMonths: range(1, $today->month - 1),
        );
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Expense, 'Cuota autónomos',
            categoryId: $cuotaCat->id,
            amountCents: 46900,
            hasIva: false,
            paidMonths: range(1, $today->month - 1),
        );
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Expense, 'Adobe Creative Cloud',
            categoryId: $software->id,
            amountCents: 6049,
            hasIva: true,
            paidMonths: range(1, $today->month - 1),
        );

        // Salario 2 200 €/mois.
        $this->createRecurringRow(
            $plan, CashFlowRowKind::Salary, 'Salario',
            amountCents: 220000,
            paidMonths: range(1, $today->month - 1),
        );

        // Quelques movements ponctuels (sans row).
        Movement::create([
            'user_id' => $user->id,
            'kind' => MovementKind::Income,
            'category_id' => $servicios->id,
            'label' => 'Factura Globex Corp.',
            'client_name' => 'Globex Corp.',
            'amount' => 180000,
            'estimated_on' => $today->addDays(20)->toDateString(),
            'paid_at' => null,
            'has_iva' => true,
            'has_irpf' => true,
            'source' => MovementSource::Manual,
        ]);

        Movement::create([
            'user_id' => $user->id,
            'kind' => MovementKind::Income,
            'category_id' => $servicios->id,
            'label' => 'Factura Stark Industries',
            'client_name' => 'Stark Industries',
            'amount' => 420000,
            'estimated_on' => $today->addDays(55)->toDateString(),
            'paid_at' => null,
            'has_iva' => true,
            'has_irpf' => true,
            'source' => MovementSource::Manual,
        ]);
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
            'has_irpf' => $rowKind === CashFlowRowKind::Income ? $hasIrpf : false,
            'sort_order' => 0,
        ]);

        $movementKind = match ($rowKind) {
            CashFlowRowKind::Income => MovementKind::Income,
            CashFlowRowKind::Expense => MovementKind::Expense,
            CashFlowRowKind::Salary => MovementKind::Salary,
        };

        foreach (range(1, 12) as $month) {
            Movement::create([
                'user_id' => $plan->user_id,
                'cash_flow_plan_id' => $plan->id,
                'cash_flow_row_id' => $row->id,
                'category_id' => $categoryId,
                'kind' => $movementKind,
                'label' => $label,
                'client_name' => $clientName,
                'amount' => $amountCents,
                'estimated_on' => CarbonImmutable::create($plan->year, $month, 15)->toDateString(),
                'paid_at' => in_array($month, $paidMonths, true) ? now() : null,
                'has_iva' => $row->has_iva,
                'has_irpf' => $row->has_irpf,
                'source' => MovementSource::Recurring,
            ]);
        }
    }
}
