<?php

namespace Tests\Feature;

use App\Enums\ChargeFrequency;
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

    public function test_store_met_a_jour_le_profil_et_cree_le_cargo_cuota(): void
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

        $this->assertDatabaseHas('recurring_charges', [
            'user_id' => $user->id,
            'label' => 'Cuota autónomos',
            'amount' => 46900,
            'frequency' => ChargeFrequency::Monthly->value,
            'day_of_month' => 30,
            'is_active' => true,
        ]);

        // Catégories par défaut créées.
        $this->assertGreaterThan(0, $user->categories()->count());
    }

    public function test_store_met_a_jour_le_cargo_cuota_existant(): void
    {
        $user = User::factory()->create();
        $user->financialProfile()->create();
        $user->recurringCharges()->create([
            'label' => 'Cuota autónomos',
            'amount' => 28000,
            'frequency' => ChargeFrequency::Monthly,
            'day_of_month' => 30,
            'next_due_on' => '2026-06-30',
            'is_active' => false,
        ]);

        $this->actingAs($user)->post('/onboarding', [
            'annualRevenue' => 30000,
            'cuotaMonthly' => 469,
            'monthlySalary' => 1500,
        ])->assertRedirect();

        $this->assertSame(1, $user->recurringCharges()->where('label', 'Cuota autónomos')->count());
        $cuota = $user->recurringCharges()->where('label', 'Cuota autónomos')->first();
        $this->assertSame(46900, $cuota->amount);
        $this->assertTrue($cuota->is_active);
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
