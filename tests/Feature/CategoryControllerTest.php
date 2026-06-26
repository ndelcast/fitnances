<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_exige_une_authentification(): void
    {
        $this->postJson('/categories', ['name' => 'Test', 'type' => 'expense'])
            ->assertUnauthorized();
    }

    public function test_un_utilisateur_peut_creer_une_categorie(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/categories', ['name' => 'Coworking', 'type' => 'expense']);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'name', 'type'])
            ->assertJson(['name' => 'Coworking', 'type' => 'expense']);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Coworking',
            'type' => 'expense',
        ]);
    }

    public function test_le_nom_est_unique_par_utilisateur_et_type(): void
    {
        $user = User::factory()->create();
        $user->categories()->create(['name' => 'Coworking', 'type' => 'expense']);

        $this->actingAs($user)
            ->postJson('/categories', ['name' => 'Coworking', 'type' => 'expense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_meme_nom_autorise_pour_un_type_different(): void
    {
        $user = User::factory()->create();
        $user->categories()->create(['name' => 'Otros', 'type' => 'expense']);

        $this->actingAs($user)
            ->postJson('/categories', ['name' => 'Otros', 'type' => 'income'])
            ->assertCreated();
    }

    public function test_type_invalide_rejete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/categories', ['name' => 'X', 'type' => 'bidon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }
}
