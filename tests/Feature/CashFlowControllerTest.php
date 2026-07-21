<?php

namespace Tests\Feature;

use App\Enums\MovementKind;
use App\Models\FinancialProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CashFlowControllerTest extends TestCase
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

    public function test_index_cree_un_plan_vide_si_inexistant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/cash-flow')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('FlujoCaja/Index')
                ->where('year', 2026)
                ->has('incomes')
                ->has('expenses')
                ->has('salary.monthly', 12)
                ->has('quarterlyTaxes.iva', 4)
                ->has('quarterlyTaxes.irpf', 4)
            );

        $this->assertNotNull($user->cashFlowPlans()->where('year', 2026)->first());
    }

    public function test_index_seed_salary_a_la_creation(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create(['monthly_salary' => 220000]);

        $this->actingAs($user)->get('/cash-flow')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('salary.monthly.0', 2200)
            );
    }

    public function test_index_avec_query_year(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/cash-flow?year=2027')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('year', 2027));
    }

    public function test_update_persiste_la_grille_via_movements(): void
    {
        $user = User::factory()->create();
        $plan = $user->cashFlowPlans()->create(['year' => 2026, 'starting_balance' => 0]);

        $payload = [
            'startingBalance' => 5000,
            'irpfExempt' => false,
            'incomes' => [
                [
                    'label' => 'Acme S.L.',
                    'clientName' => 'Acme S.L.',
                    'categoryId' => null,
                    'monthly' => [3200, 0, 3200, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    'hasIva' => true,
                    'hasIrpf' => true,
                ],
            ],
            'expenses' => [
                [
                    'label' => 'Alquiler',
                    'categoryId' => null,
                    'monthly' => array_fill(0, 12, 650),
                    'hasIva' => true,
                ],
            ],
            'salary' => [
                'label' => 'Salario',
                'monthly' => array_fill(0, 12, 2000),
            ],
        ];

        $this->actingAs($user)
            ->put('/cash-flow/2026', $payload)
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame(500000, $plan->starting_balance);
        $this->assertCount(3, $plan->rows); // income + expense + salary

        // 2 incomes (Ene, Mar) + 12 expenses + 12 salaries = 26 movements.
        $this->assertSame(2, $plan->movements()->where('kind', MovementKind::Income->value)->count());
        $this->assertSame(12, $plan->movements()->where('kind', MovementKind::Expense->value)->count());
        $this->assertSame(12, $plan->movements()->where('kind', MovementKind::Salary->value)->count());

        $firstIncome = $plan->movements()
            ->where('kind', MovementKind::Income->value)
            ->orderBy('estimated_on')
            ->first();
        $this->assertSame(320000, $firstIncome->amount);
    }

    public function test_update_persiste_la_recurrence_sur_les_rows(): void
    {
        $user = User::factory()->create();
        $plan = $user->cashFlowPlans()->create(['year' => 2026, 'starting_balance' => 0]);

        $recurringMonthly = array_fill(0, 12, 0);
        $recurringMonthly[2] = 400; // Mar : hosting annuel

        $payload = [
            'startingBalance' => 0,
            'incomes' => [
                [
                    'label' => 'Hosting Acme',
                    'clientName' => 'Hosting Acme',
                    'monthly' => $recurringMonthly,
                    'hasIva' => true,
                    'hasIrpf' => true,
                    'isRecurring' => true,
                    'recurrenceInterval' => 12,
                    'recurrenceStartMonth' => 3,
                    'recurrenceEndMonth' => 12,
                ],
                [
                    'label' => 'Puntual S.L.',
                    'clientName' => 'Puntual S.L.',
                    'monthly' => [0, 1000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    'hasIva' => true,
                    'hasIrpf' => true,
                    'isRecurring' => false,
                ],
            ],
        ];

        $this->actingAs($user)->put('/cash-flow/2026', $payload)->assertRedirect();

        $rows = $plan->fresh()->rows->keyBy('label');

        $recurring = $rows->get('Hosting Acme');
        $this->assertTrue($recurring->is_recurring);
        $this->assertSame(12, $recurring->recurrence_interval);
        $this->assertSame(3, $recurring->recurrence_start_month);
        $this->assertSame(12, $recurring->recurrence_end_month);

        $oneOff = $rows->get('Puntual S.L.');
        $this->assertFalse($oneOff->is_recurring);
        $this->assertNull($oneOff->recurrence_interval);
        $this->assertNull($oneOff->recurrence_start_month);
        $this->assertNull($oneOff->recurrence_end_month);
    }

    public function test_update_preserve_paid_at_des_movements_existantes(): void
    {
        $user = User::factory()->create();
        $plan = $user->cashFlowPlans()->create(['year' => 2026]);

        // Première sauvegarde : crée une row income avec 12 movements.
        $payload = [
            'startingBalance' => 0,
            'incomes' => [[
                'label' => 'Acme',
                'monthly' => array_fill(0, 12, 1000),
                'hasIva' => true,
                'hasIrpf' => true,
            ]],
        ];
        $this->actingAs($user)->put('/cash-flow/2026', $payload)->assertRedirect();

        // L'utilisateur marque une movement payée via /movements.
        $movement = $plan->fresh()->movements()->first();
        $movement->update(['paid_at' => now()]);

        // Deuxième sauvegarde du plan (même payload) : on doit garder paid_at.
        $payload['incomes'][0]['id'] = $plan->fresh()->rows->first()->id;
        $this->actingAs($user)->put('/cash-flow/2026', $payload)->assertRedirect();

        $this->assertNotNull($movement->fresh()->paid_at);
    }

    public function test_update_404_sur_un_plan_inexistant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/cash-flow/2030', ['startingBalance' => 0])
            ->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_modifier_le_plan_d_un_autre(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $alice->cashFlowPlans()->create(['year' => 2026]);

        $this->actingAs($bob)
            ->put('/cash-flow/2026', ['startingBalance' => 0])
            ->assertNotFound();
    }
}
