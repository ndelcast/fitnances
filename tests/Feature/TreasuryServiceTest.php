<?php

namespace Tests\Feature;

use App\Models\FinancialProfile;
use App\Models\Movement;
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
            'iva_default' => 21,
            'irpf_default' => 0,
        ]);

        Movement::factory()->income()->paid()->for($user)
            ->create(['amount' => 121000, 'has_irpf' => false]);
        Movement::factory()->expense()->paid()->for($user)
            ->create(['amount' => 50000]);

        $snapshot = app(TreasuryService::class)->snapshot($user);

        $this->assertSame(71000, $snapshot->cash);
        $this->assertSame(12322, $snapshot->provisions->iva);
        $this->assertSame(11736, $snapshot->provisions->irpf);
        $this->assertSame(71000 - 12322 - 11736, $snapshot->available);
    }

    public function test_withdrawable_retire_les_charges_restantes_du_mois(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create([
            'iva_default' => 0,
            'irpf_default' => 0,
        ]);

        // Income = expense → rendimiento neto = 0.
        Movement::factory()->income()->paid()->for($user)
            ->create(['amount' => 200000, 'has_iva' => false, 'has_irpf' => false]);
        Movement::factory()->expense()->paid()->for($user)
            ->create(['amount' => 200000, 'has_iva' => false]);

        // Charge de 800 € due dans 2 jours (mois en cours), non payée.
        Movement::factory()->expense()->planned()->for($user)->create([
            'amount' => 80000,
            'has_iva' => false,
            'estimated_on' => CarbonImmutable::today()->addDays(2),
        ]);

        $snapshot = app(TreasuryService::class)->snapshot($user);

        $this->assertSame(0, $snapshot->cash);
        $this->assertSame(0, $snapshot->provisions->total());
        $this->assertSame(-80000, $snapshot->withdrawableThisMonth);
    }

    public function test_previsionnel_90_jours(): void
    {
        $user = User::factory()->create();

        Movement::factory()->income()->paid()->for($user)->create(['amount' => 100000]);

        // Income future dans la fenêtre 90 j.
        Movement::factory()->income()->planned()->for($user)->create([
            'amount' => 300000,
            'estimated_on' => CarbonImmutable::today()->addDays(30),
        ]);
        // Hors fenêtre.
        Movement::factory()->income()->planned()->for($user)->create([
            'amount' => 999999,
            'estimated_on' => CarbonImmutable::today()->addDays(200),
        ]);

        // 3 mensualités de 500 € dans la fenêtre 90 j.
        foreach ([5, 35, 65] as $days) {
            Movement::factory()->expense()->planned()->for($user)->create([
                'amount' => 50000,
                'estimated_on' => CarbonImmutable::today()->addDays($days),
            ]);
        }

        $forecast = app(CashForecastService::class)->forUser($user, 90);

        $this->assertSame(100000, $forecast->startingCash);
        $this->assertSame(300000, $forecast->expectedIncome);
        $this->assertSame(150000, $forecast->projectedCharges);
        $this->assertSame(250000, $forecast->projectedBalance());
    }
}
