<?php

namespace Tests\Feature;

use App\Enums\ChargeFrequency;
use App\Models\Category;
use App\Models\RecurringCharge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RecurringChargeControllerTest extends TestCase
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

    public function test_index_liste_les_cargos(): void
    {
        $user = User::factory()->create();
        RecurringCharge::factory()->for($user)->create(['label' => 'Alquiler', 'amount' => 65000]);

        $this->actingAs($user)->get('/recurring-charges')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('CargosRecurrentes/Index')
                ->has('charges', 1)
                ->where('charges.0.label', 'Alquiler')
                ->where('charges.0.amount', 650)
            );
    }

    public function test_store_calcule_next_due_on(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->for($user)->create();

        $this->actingAs($user)->post('/recurring-charges', [
            'label' => 'Coworking',
            'amount' => 290.00,
            'frequency' => 'monthly',
            'day_of_month' => 25,
            'category_id' => $category->id,
            'is_active' => true,
        ])->assertRedirect();

        $charge = RecurringCharge::firstWhere('label', 'Coworking');
        $this->assertNotNull($charge);
        $this->assertSame(29000, $charge->amount);
        // Le 25 du mois courant n'est pas dépassé → next_due_on = 2026-06-25.
        $this->assertSame('2026-06-25', $charge->next_due_on->toDateString());
    }

    public function test_store_passe_au_mois_suivant_si_jour_depasse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/recurring-charges', [
            'label' => 'Internet',
            'amount' => 50,
            'frequency' => 'monthly',
            'day_of_month' => 5,
        ])->assertRedirect();

        $charge = RecurringCharge::firstWhere('label', 'Internet');
        $this->assertSame('2026-07-05', $charge->next_due_on->toDateString());
    }

    public function test_update_recalcule_next_due_on(): void
    {
        $user = User::factory()->create();
        $charge = RecurringCharge::factory()->for($user)->create([
            'frequency' => ChargeFrequency::Monthly,
            'day_of_month' => 1,
            'next_due_on' => '2026-07-01',
        ]);

        $this->actingAs($user)->put("/recurring-charges/{$charge->id}", [
            'label' => 'Renamed',
            'amount' => 100,
            'frequency' => 'monthly',
            'day_of_month' => 28,
        ])->assertRedirect();

        $this->assertSame('2026-06-28', $charge->fresh()->next_due_on->toDateString());
    }

    public function test_toggle_inverse_is_active(): void
    {
        $user = User::factory()->create();
        $charge = RecurringCharge::factory()->for($user)->create(['is_active' => true]);

        $this->actingAs($user)->patch("/recurring-charges/{$charge->id}/toggle")->assertRedirect();

        $this->assertFalse($charge->fresh()->is_active);
    }

    public function test_destroy_supprime(): void
    {
        $user = User::factory()->create();
        $charge = RecurringCharge::factory()->for($user)->create();

        $this->actingAs($user)->delete("/recurring-charges/{$charge->id}")->assertRedirect();

        $this->assertDatabaseMissing('recurring_charges', ['id' => $charge->id]);
    }

    public function test_un_utilisateur_ne_peut_pas_modifier_le_cargo_d_un_autre(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charge = RecurringCharge::factory()->for($alice)->create();

        $this->actingAs($bob)->delete("/recurring-charges/{$charge->id}")->assertForbidden();
    }
}
