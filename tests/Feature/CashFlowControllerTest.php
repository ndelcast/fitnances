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
                ->has('quarterlyTaxes.ivaPaid', 4)
                ->has('quarterlyTaxes.irpfPaid', 4)
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
                    'paid' => [true, false, false, false, false, false, false, false, false, false, false, false],
                    'hasIva' => true,
                    'hasIrpf' => true,
                ],
            ],
            'expenses' => [
                [
                    'label' => 'Alquiler',
                    'categoryId' => null,
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
            'quarterlyTaxes' => [
                'ivaPaid' => [true, false, false, false],
                'irpfPaid' => [true, true, false, false],
            ],
        ];

        $this->actingAs($user)
            ->put('/cash-flow/2026', $payload)
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame(500000, $plan->starting_balance);
        $this->assertCount(3, $plan->rows); // income + expense + salary

        // 2 incomes (Ene, Mar) + 12 expenses + 12 salaries = 26 movements rattachées
        // aux rows + 1 IVA + 2 IRPF marqués payés (kind=tax, row=null).
        $this->assertSame(2, $plan->movements()->where('kind', MovementKind::Income->value)->count());
        $this->assertSame(12, $plan->movements()->where('kind', MovementKind::Expense->value)->count());
        $this->assertSame(12, $plan->movements()->where('kind', MovementKind::Salary->value)->count());
        $this->assertSame(3, $plan->movements()->where('kind', MovementKind::Tax->value)->count());

        // Premier mois cobrado.
        $firstIncome = $plan->movements()
            ->where('kind', MovementKind::Income->value)
            ->orderBy('estimated_on')
            ->first();
        $this->assertNotNull($firstIncome->paid_at);
        $this->assertSame(320000, $firstIncome->amount);
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
