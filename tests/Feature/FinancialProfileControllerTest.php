<?php

namespace Tests\Feature;

use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FinancialProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_renvoie_le_profil_existant(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create([
            'iva_default' => 21,
            'irpf_default' => 15,
            'cuota_monthly' => 46900,
            'intra_community' => true,
        ]);

        $this->actingAs($user)->get('/fiscal-profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('PerfilFiscal/Edit')
                ->where('profile.iva_default', 21)
                ->where('profile.cuota_monthly', 469)
                ->where('profile.intra_community', true)
            );
    }

    public function test_edit_cree_un_profil_vide_si_inexistant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/fiscal-profile')->assertOk();

        $this->assertNotNull($user->fresh()->financialProfile);
    }

    public function test_update_persiste_les_modifications(): void
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->for($user)->create();

        $this->actingAs($user)->put('/fiscal-profile', [
            'full_name' => 'Ana Pérez',
            'nif' => '12345678Z',
            'activity' => 'Consultoría',
            'province' => 'Madrid',
            'regime' => 'direct_simplified',
            'iva_default' => 10,
            'irpf_default' => 7,
            'cuota_monthly' => 80,
            'intra_community' => true,
        ])->assertRedirect();

        $profile = $user->fresh()->financialProfile;
        $this->assertSame('Ana Pérez', $profile->full_name);
        $this->assertSame(8000, $profile->cuota_monthly);
        $this->assertEquals(10, $profile->iva_default);
        $this->assertEquals(7, $profile->irpf_default);
        $this->assertTrue($profile->intra_community);
    }

    public function test_update_valide_regime(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/fiscal-profile', [
            'regime' => 'inexistant',
            'iva_default' => 21,
            'irpf_default' => 15,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('regime');
    }
}
