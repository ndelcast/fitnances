<?php

namespace Database\Seeders;

use App\Enums\ChargeFrequency;
use App\Enums\FiscalRegime;
use App\Models\Category;
use App\Models\ExpectedIncome;
use App\Models\FinancialProfile;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use App\Models\User;
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
                'cuota_monthly' => 46900, // 469 €
                'surcharge_equivalence' => false,
                'intra_community' => true,
                'currency' => 'EUR',
            ],
        );

        $servicios = Category::factory()->income()->for($user)->create(['name' => 'Servicios profesionales']);
        $software = Category::factory()->expense()->for($user)->create(['name' => 'Software / suscripciones']);
        $alquiler = Category::factory()->expense()->for($user)->create(['name' => 'Alquiler oficina']);

        // Encaissements des 3 derniers mois (3 200 € TTC).
        foreach ([0, 1, 2] as $monthsAgo) {
            Transaction::factory()->income()->for($user)->create([
                'category_id' => $servicios->id,
                'amount' => 320000,
                'label' => 'Factura Acme S.L.',
                'occurred_on' => $today->subMonthsNoOverflow($monthsAgo)->subDays(3),
            ]);
        }

        // Charges ponctuelles passées.
        Transaction::factory()->expense()->for($user)->create([
            'category_id' => $software->id,
            'amount' => 6049,
            'label' => 'Adobe Creative Cloud',
            'occurred_on' => $today->subDays(7),
        ]);

        // Charges récurrentes.
        RecurringCharge::factory()->for($user)->create([
            'category_id' => $alquiler->id,
            'label' => 'Alquiler oficina',
            'amount' => 65000,
            'frequency' => ChargeFrequency::Monthly,
            'next_due_on' => $today->addDays(4),
        ]);
        RecurringCharge::factory()->for($user)->create([
            'label' => 'Cuota autónomos',
            'amount' => 46900,
            'frequency' => ChargeFrequency::Monthly,
            'next_due_on' => $today->addDays(5),
        ]);
        RecurringCharge::factory()->for($user)->create([
            'category_id' => $software->id,
            'label' => 'Adobe Creative Cloud',
            'amount' => 6049,
            'frequency' => ChargeFrequency::Monthly,
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
    }
}
