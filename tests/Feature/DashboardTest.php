<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_dashboard_rend_les_chiffres_de_tresorerie(): void
    {
        $this->seed(DemoSeeder::class);

        $this->get('/dashboard')
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
