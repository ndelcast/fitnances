<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inscription_cree_user_profil_et_essai_puis_connecte(): void
    {
        $response = $this->post('/register', [
            'name' => 'Camille Martin',
            'email' => 'camille@example.com',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'camille@example.com')->firstOrFail();
        $this->assertNotNull($user->financialProfile, 'Un profil fiscal doit être créé.');
        $this->assertTrue($user->onTrial(), 'L\'essai doit être actif.');
    }

    public function test_connexion_avec_identifiants_valides(): void
    {
        $user = User::factory()->create(['password' => Hash::make('motdepasse123')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'motdepasse123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_connexion_echoue_avec_mauvais_mot_de_passe(): void
    {
        $user = User::factory()->create(['password' => Hash::make('motdepasse123')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'mauvais',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deconnexion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_un_utilisateur_connecte_est_redirige_depuis_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect();
    }
}
