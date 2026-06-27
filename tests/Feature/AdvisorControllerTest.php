<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdvisorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_advisor_requiere_autenticacion(): void
    {
        $this->get('/advisor')->assertRedirect('/login');
    }

    public function test_la_pagina_muestra_estado_vacio_si_no_hay_analisis(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/advisor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Advisor/Index')
                ->where('lastAnalysis', null)
            );
    }

    public function test_lanzar_analisis_llama_a_claude_y_guarda_el_payload(): void
    {
        config(['services.anthropic.api_key' => 'test-key']);

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'veredicto' => 'Buen camino, sigue así.',
                    'tono' => 'bueno',
                    'puntuacion' => 72,
                    'highlights' => [
                        ['tipo' => 'fortaleza', 'titulo' => 'Recurrencia alta', 'detalle' => '100%', 'emoji' => '✅'],
                    ],
                    'recomendaciones' => [
                        ['prioridad' => 'media', 'accion' => 'Diversifica clientes', 'impacto' => 'Reduces riesgo', 'emoji' => '🎯'],
                    ],
                    'felicitaciones' => null,
                ])]],
                'usage' => ['input_tokens' => 500, 'output_tokens' => 200],
                'model' => 'claude-opus-4-7',
            ], 200),
        ]);

        $user = User::factory()->create();
        FinancialProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/advisor/analyze')
            ->assertRedirect();

        $this->assertDatabaseCount('ai_analyses', 1);
        $analysis = AiAnalysis::firstOrFail();
        $this->assertSame($user->id, $analysis->user_id);
        $this->assertSame(72, $analysis->payload['puntuacion']);
        $this->assertSame(200, $analysis->output_tokens);
    }

    public function test_sin_api_key_la_analyze_falla_con_mensaje_de_error(): void
    {
        config(['services.anthropic.api_key' => null]);

        $user = User::factory()->create();
        FinancialProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/advisor/analyze')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('ai_analyses', 0);
    }
}
