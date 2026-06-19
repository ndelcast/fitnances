<?php

namespace Tests\Feature;

use App\Enums\ChargeFrequency;
use App\Models\ExpectedIncome;
use App\Models\FinancialProfile;
use App\Models\RecurringCharge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashForecastService;
use App\Services\TreasuryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Date figée en début de mois : fenêtres temporelles déterministes.
        CarbonImmutable::setTestNow('2026-06-10');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_snapshot_calcule_cash_provisions_et_disponible(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create([
            'urssaf_rate' => 22,
            'collects_vat' => false,
            'income_tax_rate' => 0,
        ]);

        // 4000 € encaissés, 500 € de charges → cash = 3500 €.
        Transaction::factory()->income()->for($user)->create(['amount' => 400000]);
        Transaction::factory()->expense()->for($user)->create(['amount' => 50000]);

        $snapshot = app(TreasuryService::class)->snapshot($user);

        $this->assertSame(350000, $snapshot->cash);
        $this->assertSame(88000, $snapshot->provisions->urssaf); // 22% de 4000 €
        $this->assertSame(262000, $snapshot->available); // 3500 € - 880 €
    }

    public function test_withdrawable_retire_les_charges_restantes_du_mois(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create([
            'urssaf_rate' => 0, // isole l'effet des charges
            'collects_vat' => false,
            'income_tax_rate' => 0,
        ]);

        Transaction::factory()->income()->for($user)->create(['amount' => 200000]); // 2000 €

        // Charge mensuelle de 800 € due dans 2 jours (donc ce mois-ci).
        RecurringCharge::factory()->for($user)->create([
            'amount' => 80000,
            'frequency' => ChargeFrequency::Monthly,
            'next_due_on' => CarbonImmutable::today()->addDays(2),
        ]);

        $snapshot = app(TreasuryService::class)->snapshot($user);

        $this->assertSame(200000, $snapshot->available);
        $this->assertSame(120000, $snapshot->withdrawableThisMonth); // 2000 € - 800 €
    }

    public function test_previsionnel_90_jours(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->income()->for($user)->create(['amount' => 100000]); // cash 1000 €

        ExpectedIncome::factory()->for($user)->create([
            'amount' => 300000,
            'expected_on' => CarbonImmutable::today()->addDays(30),
        ]);
        // Facture hors fenêtre 90j : ne doit pas compter.
        ExpectedIncome::factory()->for($user)->create([
            'amount' => 999999,
            'expected_on' => CarbonImmutable::today()->addDays(200),
        ]);

        RecurringCharge::factory()->for($user)->create([
            'amount' => 50000,
            'frequency' => ChargeFrequency::Monthly,
            'next_due_on' => CarbonImmutable::today()->addDays(5),
        ]);

        $forecast = app(CashForecastService::class)->forUser($user, 90);

        $this->assertSame(100000, $forecast->startingCash);
        $this->assertSame(300000, $forecast->expectedIncome);
        $this->assertSame(150000, $forecast->projectedCharges); // 3 mensualités de 500 € sur 90 j
        $this->assertSame(250000, $forecast->projectedBalance()); // 1000 + 3000 - 1500
    }
}
