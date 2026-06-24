<?php

namespace Database\Seeders;

use App\Enums\ChargeFrequency;
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
            ['name' => 'Camille Martin', 'password' => Hash::make('password')],
        );

        FinancialProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'urssaf_rate' => 22,
                'collects_vat' => true,
                'vat_rate' => 20,
                'income_tax_rate' => 5,
                'currency' => 'EUR',
            ],
        );

        $conseil = Category::factory()->income()->for($user)->create(['name' => 'Conseil']);
        $outils = Category::factory()->expense()->for($user)->create(['name' => 'Outils & logiciels']);

        // Encaissements des 3 derniers mois (3 600 € TTC chacun).
        foreach ([0, 1, 2] as $monthsAgo) {
            Transaction::factory()->income()->for($user)->create([
                'category_id' => $conseil->id,
                'amount' => 360000,
                'label' => 'Mission conseil',
                'occurred_on' => $today->subMonthsNoOverflow($monthsAgo)->subDays(3),
            ]);
        }

        // Charges ponctuelles passées.
        Transaction::factory()->expense()->for($user)->create([
            'category_id' => $outils->id,
            'amount' => 30000,
            'label' => 'Licence logicielle',
            'occurred_on' => $today->subDays(20),
        ]);

        // Charges récurrentes.
        RecurringCharge::factory()->for($user)->create([
            'label' => 'Espace de coworking',
            'amount' => 25000,
            'frequency' => ChargeFrequency::Monthly,
            'next_due_on' => $today->addDays(4),
        ]);
        RecurringCharge::factory()->for($user)->create([
            'label' => 'Comptable',
            'amount' => 8000,
            'frequency' => ChargeFrequency::Monthly,
            'next_due_on' => $today->addDays(9),
        ]);
        RecurringCharge::factory()->for($user)->create([
            'label' => 'Suite logicielle',
            'amount' => 4900,
            'frequency' => ChargeFrequency::Monthly,
            'next_due_on' => $today->addDays(14),
        ]);

        // Factures à venir (prévisionnel 90 jours).
        ExpectedIncome::factory()->for($user)->create([
            'label' => 'Facture 2026-014',
            'client_name' => 'Atelier Dubois',
            'amount' => 420000,
            'expected_on' => $today->addDays(25),
        ]);
        ExpectedIncome::factory()->for($user)->create([
            'label' => 'Facture 2026-015',
            'client_name' => 'Studio Lemaire',
            'amount' => 300000,
            'expected_on' => $today->addDays(55),
        ]);
    }
}
