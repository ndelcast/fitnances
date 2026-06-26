<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
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

    public function test_index_liste_les_transactions(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->income()->for($user)->create([
            'amount' => 121000,
            'label' => 'Factura Acme',
            'occurred_on' => '2026-06-15',
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'amount' => 5000,
            'label' => 'Café cliente',
            'occurred_on' => '2026-07-01', // futuro → previsto
        ]);

        $this->actingAs($user)->get('/transacciones')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transacciones/Index')
                ->has('transactions', 2)
                ->where('transactions.0.label', 'Café cliente')
                ->where('transactions.0.status', 'previsto')
                ->where('transactions.1.label', 'Factura Acme')
                ->where('transactions.1.status', 'realizado')
                ->where('transactions.1.amount', 1210)
                ->has('categories')
            );
    }

    public function test_store_crée_une_transaction(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->income()->for($user)->create();

        $this->actingAs($user)->post('/transacciones', [
            'type' => 'income',
            'label' => 'Factura nueva',
            'amount' => 1500.50,
            'category_id' => $category->id,
            'iva_rate' => 21,
            'irpf_rate' => 15,
            'occurred_on' => '2026-06-15',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'label' => 'Factura nueva',
            'amount' => 150050,
            'type' => TransactionType::Income->value,
        ]);
    }

    public function test_store_rejette_des_donnees_invalides(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/transacciones', [
            'type' => 'bidon',
            'label' => '',
            'amount' => -10,
            'occurred_on' => 'pas-une-date',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'label', 'amount', 'occurred_on']);
    }

    public function test_update_modifie_une_transaction(): void
    {
        $user = User::factory()->create();
        $tx = Transaction::factory()->expense()->for($user)->create([
            'amount' => 5000,
            'label' => 'Antiguo',
            'occurred_on' => '2026-06-01',
        ]);

        $this->actingAs($user)->put("/transacciones/{$tx->id}", [
            'type' => 'expense',
            'label' => 'Nuevo',
            'amount' => 75,
            'occurred_on' => '2026-06-02',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'label' => 'Nuevo',
            'amount' => 7500,
        ]);
    }

    public function test_un_utilisateur_ne_peut_pas_modifier_la_transaction_d_un_autre(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $tx = Transaction::factory()->for($alice)->create();

        $this->actingAs($bob)->put("/transacciones/{$tx->id}", [
            'type' => 'expense',
            'label' => 'Hack',
            'amount' => 1,
            'occurred_on' => '2026-06-01',
        ])->assertForbidden();
    }

    public function test_destroy_supprime_une_transaction(): void
    {
        $user = User::factory()->create();
        $tx = Transaction::factory()->for($user)->create();

        $this->actingAs($user)->delete("/transacciones/{$tx->id}")->assertRedirect();

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }

    public function test_realize_passe_la_date_a_aujourd_hui(): void
    {
        $user = User::factory()->create();
        $tx = Transaction::factory()->income()->for($user)->create([
            'occurred_on' => '2026-07-15', // futuro → previsto
        ]);

        $this->actingAs($user)->patch("/transacciones/{$tx->id}/realize")->assertRedirect();

        $this->assertSame('2026-06-20', $tx->fresh()->occurred_on->toDateString());
    }
}
