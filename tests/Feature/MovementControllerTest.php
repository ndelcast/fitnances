<?php

namespace Tests\Feature;

use App\Enums\MovementKind;
use App\Models\Category;
use App\Models\Movement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MovementControllerTest extends TestCase
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

    public function test_index_liste_les_movements(): void
    {
        $user = User::factory()->create();
        Movement::factory()->income()->paid()->for($user)->create([
            'amount' => 121000,
            'label' => 'Factura Acme',
            'estimated_on' => '2026-06-15',
        ]);

        $this->actingAs($user)->get('/movements')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Movements/Index')
                ->has('movements', 1)
                ->where('movements.0.label', 'Factura Acme')
                ->where('movements.0.is_paid', true)
                ->where('movements.0.amount', 1210)
            );
    }

    public function test_store_cree_un_movement(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->income()->for($user)->create();

        $this->actingAs($user)->post('/movements', [
            'kind' => 'income',
            'label' => 'Factura nueva',
            'amount' => 1500.50,
            'category_id' => $category->id,
            'has_iva' => true,
            'has_irpf' => true,
            'iva_rate' => 21,
            'irpf_rate' => 15,
            'estimated_on' => '2026-06-15',
            'paid' => true,
        ])->assertRedirect();

        $movement = Movement::firstWhere('label', 'Factura nueva');
        $this->assertSame(150050, $movement->amount);
        $this->assertSame(MovementKind::Income, $movement->kind);
        $this->assertNotNull($movement->paid_at);
    }

    public function test_update_modifie_un_movement(): void
    {
        $user = User::factory()->create();
        $m = Movement::factory()->expense()->for($user)->create([
            'amount' => 5000,
            'label' => 'Antiguo',
        ]);

        $this->actingAs($user)->put("/movements/{$m->id}", [
            'kind' => 'expense',
            'label' => 'Nuevo',
            'amount' => 75,
            'estimated_on' => '2026-06-02',
            'has_iva' => true,
        ])->assertRedirect();

        $this->assertSame('Nuevo', $m->fresh()->label);
        $this->assertSame(7500, $m->fresh()->amount);
    }

    public function test_un_utilisateur_ne_peut_pas_modifier_le_movement_d_un_autre(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $m = Movement::factory()->for($alice)->create();

        $this->actingAs($bob)->put("/movements/{$m->id}", [
            'kind' => 'expense',
            'label' => 'Hack',
            'amount' => 1,
            'estimated_on' => '2026-06-01',
        ])->assertForbidden();
    }

    public function test_destroy_supprime(): void
    {
        $user = User::factory()->create();
        $m = Movement::factory()->for($user)->create();

        $this->actingAs($user)->delete("/movements/{$m->id}")->assertRedirect();
        $this->assertDatabaseMissing('movements', ['id' => $m->id]);
    }

    public function test_toggle_paid_inverse_le_statut(): void
    {
        $user = User::factory()->create();
        $m = Movement::factory()->income()->for($user)->create(['paid_at' => null]);

        $this->actingAs($user)->patch("/movements/{$m->id}/toggle-paid")->assertRedirect();
        $this->assertNotNull($m->fresh()->paid_at);

        $this->actingAs($user)->patch("/movements/{$m->id}/toggle-paid")->assertRedirect();
        $this->assertNull($m->fresh()->paid_at);
    }
}
