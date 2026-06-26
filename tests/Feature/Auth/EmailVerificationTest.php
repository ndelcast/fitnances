<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\WelcomeAndVerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_inscription_envoie_le_mail_de_bienvenue_avec_lien(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::firstWhere('email', 'ana@test.com');
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, WelcomeAndVerifyEmail::class);
    }

    public function test_le_lien_signe_verifie_l_email(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('dashboard').'?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_un_utilisateur_non_authentifie_ne_peut_pas_verifier(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($url)->assertRedirect('/login');
    }

    public function test_lien_invalide_rejete(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/email/verify/'.$user->id.'/badhash')
            ->assertForbidden();
    }

    public function test_renvoi_du_lien_de_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertRedirect();

        Notification::assertSentTo($user, WelcomeAndVerifyEmail::class);
    }

    public function test_renvoi_redirige_si_deja_verifie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertRedirect(route('dashboard'));
    }

    public function test_la_page_de_notice_affiche_le_composant_inertia(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/email/verify')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
    }
}
