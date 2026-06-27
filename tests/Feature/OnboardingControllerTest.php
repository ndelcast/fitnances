<?php

namespace Tests\Feature;

use App\Enums\MovementKind;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_rend_la_page_avec_valeurs_pre_remplies(): void
    {
        $user = User::factory()->create();
        $user->financialProfile()->create([
            'cuota_monthly' => 30000,
            'monthly_salary' => 200000,
        ]);

        $this->actingAs($user)->get('/onboarding')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Asistente/Index')
                ->where('initial.cuotaMonthly', 300)
                ->where('initial.monthlySalary', 2000)
            );
    }

    public function test_store_cree_le_profil_et_12_movements_cuota(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/onboarding', [
            'annualRevenue' => 50000,
            'cuotaMonthly' => 469,
            'monthlySalary' => 2200,
        ])->assertRedirect('/cash-flow');

        $profile = $user->fresh()->financialProfile;
        $this->assertSame(46900, $profile->cuota_monthly);
        $this->assertSame(220000, $profile->monthly_salary);
        $this->assertNotNull($profile->onboarded_at);

        // 12 movements expense pour la cuota autónomos.
        $cuotaMovements = $user->movements()
            ->where('label', 'Cuota autónomos')
            ->where('kind', MovementKind::Expense->value)
            ->get();
        $this->assertCount(12, $cuotaMovements);
        $this->assertSame(46900, $cuotaMovements->first()->amount);

        // Catégories par défaut créées.
        $this->assertGreaterThan(0, $user->categories()->count());
    }

    public function test_store_remplace_les_movements_cuota_existants(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/onboarding', [
            'annualRevenue' => 30000,
            'cuotaMonthly' => 280,
            'monthlySalary' => 1500,
        ])->assertRedirect();

        // Deuxième appel : doit remplacer les anciens.
        $this->actingAs($user)->post('/onboarding', [
            'annualRevenue' => 30000,
            'cuotaMonthly' => 469,
            'monthlySalary' => 1500,
        ])->assertRedirect();

        $this->assertCount(12, $user->movements()->where('label', 'Cuota autónomos')->get());
        $this->assertSame(46900, $user->movements()->where('label', 'Cuota autónomos')->first()->amount);
    }

    public function test_store_persiste_le_capital_inicial_en_starting_balance(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/onboarding', [
            'startingBalance' => 3000,
            'annualRevenue' => 40000,
            'cuotaMonthly' => 300,
            'monthlySalary' => 1800,
        ])->assertRedirect();

        $plan = $user->cashFlowPlans()->firstOrFail();
        $this->assertSame(300000, $plan->starting_balance);

        // Second appel : doit mettre à jour le capital initial.
        $this->actingAs($user)->post('/onboarding', [
            'startingBalance' => 5500,
            'annualRevenue' => 40000,
            'cuotaMonthly' => 300,
            'monthlySalary' => 1800,
        ])->assertRedirect();

        $this->assertSame(550000, $plan->fresh()->starting_balance);
    }

    public function test_store_valide_les_montants(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/onboarding', [
            'annualRevenue' => -10,
            'cuotaMonthly' => null,
            'monthlySalary' => null,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['annualRevenue', 'cuotaMonthly', 'monthlySalary']);
    }
}
