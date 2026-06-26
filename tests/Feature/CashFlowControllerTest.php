<?php

namespace Tests\Feature;

use App\Enums\ChargeFrequency;
use App\Models\FinancialProfile;
use App\Models\RecurringCharge;
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

        $this->actingAs($user)->get('/flujo-caja')
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

    public function test_index_seed_salary_et_charges_recurrentes_a_la_creation(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create(['monthly_salary' => 220000]);
        RecurringCharge::factory()->for($user)->create([
            'label' => 'Alquiler',
            'amount' => 65000,
            'frequency' => ChargeFrequency::Monthly,
        ]);

        $this->actingAs($user)->get('/flujo-caja')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('salary.monthly.0', 2200)
                ->where('expenses.0.label', 'Alquiler')
                ->where('expenses.0.monthly.0', 650)
            );
    }

    public function test_index_avec_query_year(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/flujo-caja?year=2027')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('year', 2027));
    }

    public function test_update_persiste_la_grille(): void
    {
        $user = User::factory()->create();
        $plan = $user->cashFlowPlans()->create(['year' => 2026, 'starting_balance' => 420000]);

        $payload = [
            'startingBalance' => 5000,
            'irpfExempt' => false,
            'incomes' => [
                [
                    'label' => 'Acme S.L.',
                    'clientName' => 'Acme S.L.',
                    'categoryId' => null,
                    'monthly' => [3200, 0, 3200, 0, 3200, 0, 3200, 0, 3200, 0, 3200, 0],
                    'paid' => [true, false, true, false, false, false, false, false, false, false, false, false],
                ],
            ],
            'expenses' => [
                [
                    'label' => 'Alquiler',
                    'categoryId' => null,
                    'monthly' => array_fill(0, 12, 650),
                    'paid' => array_fill(0, 12, false),
                ],
            ],
            'salary' => [
                'label' => 'Salario',
                'monthly' => array_fill(0, 12, 2000),
                'paid' => array_fill(0, 12, false),
            ],
            'quarterlyTaxes' => [
                'iva' => [1500, 1600, 1700, 1800],
                'irpf' => [800, 850, 900, 950],
            ],
        ];

        $this->actingAs($user)
            ->put('/flujo-caja/2026', $payload)
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame(500000, $plan->starting_balance);
        $this->assertCount(1, $plan->rows()->where('kind', 'income')->get());
        $this->assertCount(1, $plan->rows()->where('kind', 'expense')->get());
        $this->assertCount(1, $plan->rows()->where('kind', 'salary')->get());
        $this->assertSame(4, $plan->quarterlyTaxes()->where('kind', 'iva')->count());

        $expenseRow = $plan->rows()->where('kind', 'expense')->first();
        $this->assertSame(12, $expenseRow->cells()->count());
        $this->assertSame(65000, $expenseRow->cells()->where('month', 1)->first()->amount);
    }

    public function test_update_404_sur_un_plan_inexistant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/flujo-caja/2030', ['startingBalance' => 0])
            ->assertNotFound();
    }

    public function test_un_utilisateur_ne_peut_pas_modifier_le_plan_d_un_autre(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $alice->cashFlowPlans()->create(['year' => 2026]);

        $this->actingAs($bob)
            ->put('/flujo-caja/2026', ['startingBalance' => 0])
            ->assertNotFound();
    }
}
