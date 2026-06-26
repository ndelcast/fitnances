<?php

namespace Database\Seeders;

use App\Enums\ChargeFrequency;
use App\Enums\FiscalRegime;
use App\Models\ExpectedIncome;
use App\Models\FinancialProfile;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashFlowPlanService;
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
            ['name' => 'Nicolas del Castillo', 'password' => Hash::make('password')],
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
        $cuota = $user->categories()->where('name', 'Cuota autónomos')->firstOrFail();

        // Encaissements des 3 derniers mois (3 200 € TTC).
        foreach ([0, 1, 2] as $monthsAgo) {
            Transaction::factory()->income()->for($user)->create([
                'category_id' => $servicios->id,
                'amount' => 320000,
                'iva_rate' => 21,
                'irpf_rate' => 15,
                'label' => 'Factura Acme S.L.',
                'occurred_on' => $today->subMonthsNoOverflow($monthsAgo)->subDays(3),
            ]);
        }

        // Factura prevista à 30 jours (income futur).
        Transaction::factory()->income()->for($user)->create([
            'category_id' => $servicios->id,
            'amount' => 180000,
            'iva_rate' => 21,
            'irpf_rate' => 15,
            'label' => 'Factura Globex Corp.',
            'occurred_on' => $today->addDays(20),
        ]);

        // Charge ponctuelle passée.
        Transaction::factory()->expense()->for($user)->create([
            'category_id' => $software->id,
            'amount' => 6049,
            'iva_rate' => 21,
            'label' => 'Adobe Creative Cloud',
            'occurred_on' => $today->subDays(7),
        ]);

        // Charges récurrentes.
        RecurringCharge::factory()->for($user)->create([
            'category_id' => $alquiler->id,
            'label' => 'Alquiler oficina',
            'amount' => 65000,
            'frequency' => ChargeFrequency::Monthly,
            'day_of_month' => 1,
            'next_due_on' => $today->addDays(4),
        ]);
        RecurringCharge::factory()->for($user)->create([
            'category_id' => $cuota->id,
            'label' => 'Cuota autónomos',
            'amount' => 46900,
            'frequency' => ChargeFrequency::Monthly,
            'day_of_month' => 30,
            'next_due_on' => $today->addDays(5),
        ]);
        RecurringCharge::factory()->for($user)->create([
            'category_id' => $software->id,
            'label' => 'Adobe Creative Cloud',
            'amount' => 6049,
            'frequency' => ChargeFrequency::Monthly,
            'day_of_month' => 15,
            'next_due_on' => $today->addDays(15),
        ]);

        // Facturas previstas (próximos 90 días).
        ExpectedIncome::factory()->for($user)->create([
            'label' => 'Factura 2026-014',
            'client_name' => 'Globex Corp.',
            'amount' => 180000,
            'expected_on' => $today->addDays(25),
        ]);
        ExpectedIncome::factory()->for($user)->create([
            'label' => 'Factura 2026-015',
            'client_name' => 'Stark Industries',
            'amount' => 420000,
            'expected_on' => $today->addDays(55),
        ]);

        // Plan de tesorería pour l'année en cours (peuplé via le service).
        app(CashFlowPlanService::class)->forYear($user->fresh(), $today->year);
    }
}
