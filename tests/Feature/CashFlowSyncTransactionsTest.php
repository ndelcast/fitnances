<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\FinancialProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashFlowPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowSyncTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-06-20');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_save_generates_one_transaction_per_non_zero_cell(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create([
            'iva_default' => 21,
            'irpf_default' => 15,
        ]);
        $plan = $user->cashFlowPlans()->create(['year' => 2026]);

        app(CashFlowPlanService::class)->save($plan, [
            'startingBalance' => 0,
            'incomes' => [
                [
                    'label' => 'Acme S.L.',
                    'clientName' => 'Acme S.L.',
                    'monthly' => [3200, 0, 3200, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    'paid' => array_fill(0, 12, false),
                    'hasIva' => true,
                ],
            ],
            'expenses' => [
                [
                    'label' => 'Alquiler',
                    'monthly' => array_fill(0, 12, 650),
                    'paid' => array_fill(0, 12, false),
                    'hasIva' => true,
                ],
            ],
            'salary' => [
                'label' => 'Salario',
                'monthly' => array_fill(0, 12, 2000),
                'paid' => array_fill(0, 12, false),
            ],
        ]);

        // 2 ingresos (Ene, Mar) + 12 gastos (Alquiler) = 14 transactions.
        // Le salaire n'est PAS matérialisé en transactions.
        $this->assertSame(14, $user->transactions()->where('cash_flow_plan_id', $plan->id)->count());

        $first = $user->transactions()->where('label', 'Acme S.L.')->orderBy('occurred_on')->first();
        $this->assertSame(TransactionType::Income, $first->type);
        $this->assertSame(320000, $first->amount);
        $this->assertEquals(21, (float) $first->iva_rate);
        $this->assertEquals(15, (float) $first->irpf_rate);
        $this->assertSame('2026-01-15', $first->occurred_on->toDateString());
    }

    public function test_save_regenerates_transactions_on_each_call(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create();
        $plan = $user->cashFlowPlans()->create(['year' => 2026]);

        $payload = [
            'incomes' => [[
                'label' => 'A',
                'monthly' => array_fill(0, 12, 100),
                'paid' => array_fill(0, 12, false),
                'hasIva' => true,
            ]],
        ];

        app(CashFlowPlanService::class)->save($plan, $payload);
        $this->assertSame(12, $user->transactions()->count());

        // Réduit à 3 mois → ne reste que 3 transactions liées au plan.
        $payload['incomes'][0]['monthly'] = [100, 100, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        app(CashFlowPlanService::class)->save($plan, $payload);

        $this->assertSame(3, $user->transactions()->where('cash_flow_plan_id', $plan->id)->count());
    }

    public function test_sin_iva_donne_iva_rate_zero_sur_la_transaction(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create(['iva_default' => 21]);
        $plan = $user->cashFlowPlans()->create(['year' => 2026]);

        app(CashFlowPlanService::class)->save($plan, [
            'incomes' => [[
                'label' => 'Cliente UE',
                'monthly' => [1000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                'paid' => array_fill(0, 12, false),
                'hasIva' => false,
            ]],
        ]);

        $tx = $user->transactions()->where('label', 'Cliente UE')->first();
        $this->assertEquals(0, (float) $tx->iva_rate);
    }

    public function test_transaction_planifiee_ne_peut_etre_modifiee_via_l_api(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create();
        $plan = $user->cashFlowPlans()->create(['year' => 2026]);

        app(CashFlowPlanService::class)->save($plan, [
            'incomes' => [[
                'label' => 'A',
                'monthly' => [100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                'paid' => array_fill(0, 12, false),
                'hasIva' => true,
            ]],
        ]);

        $tx = Transaction::firstWhere('label', 'A');

        $this->actingAs($user)->putJson("/transacciones/{$tx->id}", [
            'type' => 'income',
            'label' => 'Hacked',
            'amount' => 999,
            'occurred_on' => '2026-06-15',
        ])->assertStatus(422);

        $this->actingAs($user)->deleteJson("/transacciones/{$tx->id}")->assertStatus(422);
    }

    public function test_les_transactions_manuelles_ne_sont_pas_touchees_par_la_sync(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create();
        $plan = $user->cashFlowPlans()->create(['year' => 2026]);

        // Une transaction manuelle (sans cash_flow_plan_id).
        $manual = Transaction::factory()->income()->for($user)->create([
            'label' => 'Manual',
            'amount' => 50000,
        ]);

        app(CashFlowPlanService::class)->save($plan, [
            'incomes' => [[
                'label' => 'Plan',
                'monthly' => [100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                'paid' => array_fill(0, 12, false),
                'hasIva' => true,
            ]],
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $manual->id,
            'label' => 'Manual',
            'cash_flow_plan_id' => null,
        ]);
    }
}
