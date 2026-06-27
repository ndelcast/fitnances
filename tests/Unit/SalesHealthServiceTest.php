<?php

namespace Tests\Unit;

use App\Enums\MovementSource;
use App\Models\Movement;
use App\Models\User;
use App\Services\SalesHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SalesHealthService
    {
        return new SalesHealthService;
    }

    public function test_recurrencia_egale_100_quand_tout_le_chiffre_est_recurrent(): void
    {
        $user = User::factory()->create();
        for ($m = 1; $m <= 12; $m++) {
            Movement::factory()->income()->create([
                'user_id' => $user->id,
                'source' => MovementSource::Recurring,
                'amount' => 300000,
                'estimated_on' => CarbonImmutable::create(2026, $m, 15),
            ]);
        }

        $sales = $this->service()->forYear($user, 2026);

        $this->assertSame(100, $sales->recurrenciaPct);
        $this->assertSame(3600000, $sales->totalAnnualIncome);
    }

    public function test_recurrencia_distingue_recurrent_et_ponctuel(): void
    {
        $user = User::factory()->create();
        Movement::factory()->income()->create([
            'user_id' => $user->id,
            'source' => MovementSource::Recurring,
            'amount' => 600000, // 6 000 € récurrent
            'estimated_on' => CarbonImmutable::create(2026, 1, 15),
        ]);
        Movement::factory()->income()->create([
            'user_id' => $user->id,
            'source' => MovementSource::Manual,
            'amount' => 400000, // 4 000 € ponctuel
            'estimated_on' => CarbonImmutable::create(2026, 2, 15),
        ]);

        $sales = $this->service()->forYear($user, 2026);

        $this->assertSame(60, $sales->recurrenciaPct);
    }

    public function test_dso_moyen_sur_les_factures_avec_issued_on_et_paid_at(): void
    {
        $user = User::factory()->create();
        // 3 factures émises et payées avec délais 10 / 20 / 30 jours.
        foreach ([10, 20, 30] as $i => $delay) {
            $issued = CarbonImmutable::create(2026, $i + 1, 1);
            Movement::factory()->income()->create([
                'user_id' => $user->id,
                'amount' => 100000,
                'issued_on' => $issued,
                'estimated_on' => $issued->addDays($delay),
                'paid_at' => $issued->addDays($delay)->startOfDay(),
            ]);
        }

        $sales = $this->service()->forYear($user, 2026);

        $this->assertSame(20, $sales->avgDsoDays);
        $this->assertSame(3, $sales->dsoSampleSize);
    }

    public function test_pendientes_compte_les_factures_emises_et_pas_encore_payees(): void
    {
        $user = User::factory()->create();
        $past = CarbonImmutable::today()->subDays(5);
        $future = CarbonImmutable::today()->addDays(10);

        Movement::factory()->income()->create([
            'user_id' => $user->id,
            'amount' => 200000,
            'issued_on' => $past,
            'estimated_on' => $past->addDays(15),
            'paid_at' => null,
        ]);
        // Émise dans le futur → pas encore "pendiente" (pas due).
        Movement::factory()->income()->create([
            'user_id' => $user->id,
            'amount' => 100000,
            'issued_on' => $future,
            'estimated_on' => $future->addDays(15),
            'paid_at' => null,
        ]);
        // Payée → ne compte pas.
        Movement::factory()->income()->paid()->create([
            'user_id' => $user->id,
            'amount' => 50000,
            'issued_on' => $past,
            'estimated_on' => $past->addDays(15),
        ]);

        $sales = $this->service()->forYear($user, CarbonImmutable::today()->year);

        $this->assertSame(1, $sales->pendientesCount);
        $this->assertSame(200000, $sales->pendientesAmount);
    }
}
