<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_dashboard_exige_une_authentification(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_le_dashboard_rend_les_chiffres_de_tresorerie(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::where('email', 'demo@fitnances.app')->firstOrFail();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('user.name', 'Camille Martin')
                ->has('headline')
                ->has('available')
                ->has('cash')
                ->has('provisions.total')
                ->has('forecast.projectedBalance')
            );
    }
}
