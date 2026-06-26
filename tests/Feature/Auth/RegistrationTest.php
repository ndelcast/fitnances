<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_inscription_cree_profil_fiscal_et_categories_par_defaut(): void
    {
        $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'ana@test.com')->firstOrFail();

        $this->assertNotNull($user->financialProfile);
        $this->assertGreaterThan(0, $user->categories()->where('type', 'income')->count());
        $this->assertGreaterThan(0, $user->categories()->where('type', 'expense')->count());
    }
}
