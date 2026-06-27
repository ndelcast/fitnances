<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Demande à Claude une analyse financière personnalisée pour un
 * autónomo. Collecte les chiffres clés (cashflow, sustainable salary,
 * sales, renta), formule un prompt d'expert et parse la réponse JSON.
 */
final class AdvisorService
{
    public function __construct(
        private readonly SustainableSalaryService $sustainable,
        private readonly SalesHealthService $sales,
        private readonly RentaProvisionService $renta,
        private readonly BusinessHealthService $health,
    ) {}

    /**
     * Lance une analyse pour l'utilisateur et persiste le résultat.
     */
    public function analyze(User $user): AiAnalysis
    {
        $input = $this->collectInput($user);
        [$payload, $usage] = $this->callClaude($input);

        return AiAnalysis::create([
            'user_id' => $user->id,
            'input' => $input,
            'payload' => $payload,
            'model' => config('services.anthropic.model'),
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
        ]);
    }

    /**
     * Rassemble les chiffres pertinents pour l'analyse.
     *
     * @return array<string, mixed>
     */
    public function collectInput(User $user): array
    {
        $today = CarbonImmutable::today();
        $year = $today->year;

        $health = $this->health->forUser($user, $year);
        $sales = $this->sales->forYear($user, $year);
        $renta = $this->renta->forYear($user, $year);
        $plan = $user->cashFlowPlans()->where('year', $year)->first();
        $startingCash = (int) ($plan?->starting_balance ?? 0);
        // Sustainable se calcule sans le capital initial pour ne pas le brûler.
        $sim = $this->sustainable->forYear($user, $year, 0);

        return [
            'fecha_analisis' => $today->toDateString(),
            'ano_fiscal' => $year,
            'caja' => [
                'saldo_inicial_euros' => $startingCash / 100,
                'sueldo_sostenible_max_euros_mes' => $sim->sustainableSalary / 100,
                'sueldo_actual_euros_mes' => $health->currentSalary / 100,
                'mes_critico_trayectoria' => $sim->bottleneckMonth,
                'min_balance_acumulado_fin_ano_euros' => $sim->yearEndAfterQ4 / 100,
            ],
            'fiscal' => [
                'rendimiento_neto_anual_euros' => $renta->rendimientoNetoAnnual / 100,
                'base_imponible_euros' => $renta->baseImponible / 100,
                'irpf_renta_anual_euros' => $renta->rentaIrpf / 100,
                'tramo_marginal_pct' => $renta->marginalRate * 100,
                'modelo_130_anual_euros' => $renta->modelo130Annual / 100,
                'retenciones_anuales_euros' => $renta->retentionsAnnual / 100,
                'restante_renta_euros' => $renta->restanteRenta / 100,
                'devolucion_esperada_euros' => $renta->expectedRefund / 100,
            ],
            'comercial' => [
                'ingresos_anuales_totales_euros' => $sales->totalAnnualIncome / 100,
                'recurrencia_pct' => $sales->recurrenciaPct,
                'dso_dias_medios' => $sales->avgDsoDays,
                'facturas_pendientes_count' => $sales->pendientesCount,
                'facturas_pendientes_importe_euros' => $sales->pendientesAmount / 100,
            ],
            'salud_global' => [
                'score_sueldo' => $health->sueldoScore,
                'score_colchon' => $health->colchonScore,
                'score_recurrencia' => $health->recurrenciaScore,
                'score_global' => $health->globalScore,
                'meses_colchon' => round($health->runwayMonths, 1),
            ],
        ];
    }

    /**
     * Appelle l'API Claude et retourne [payload JSON parsé, usage].
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, int>}
     */
    private function callClaude(array $input): array
    {
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException('ANTHROPIC_API_KEY no está configurada en el .env.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => config('services.anthropic.version'),
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(config('services.anthropic.endpoint'), [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 2048,
                'system' => $this->systemPrompt(),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->userPrompt($input),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Claude API error: '.$response->status().' '.$response->body());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';

        // Le modèle peut entourer le JSON de ```json ... ``` ; on extrait.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            $text = $m[1];
        } else {
            // Sinon, on suppose que la première { jusqu'à la dernière } est le JSON.
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $payload = json_decode($text, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Réponse Claude non parsable en JSON: '.$text);
        }

        return [$payload, $data['usage'] ?? []];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
En tant qu'expert financier especializado en autónomos españoles, debes analizar la situación económica de un autónomo y darle consejos concretos, accionables y personalizados. Hablas español de España (no latinoamericano). Tu tono es directo, empático y motivador, como un coach financiero experimentado.

Tu respuesta DEBE ser un objeto JSON estricto (sin comentarios, sin markdown alrededor) con esta estructura exacta :

{
  "veredicto": "string corto (1 frase max 100 caracteres) que resume el estado general",
  "tono": "excelente | bueno | atencion | critico",
  "puntuacion": 0-100,
  "highlights": [
    {
      "tipo": "fortaleza | aviso | riesgo | oportunidad",
      "titulo": "string corto (max 60 caracteres)",
      "detalle": "string descriptivo (1-2 frases max 200 caracteres)",
      "emoji": "1 emoji representativo"
    }
  ],
  "recomendaciones": [
    {
      "prioridad": "alta | media | baja",
      "accion": "string verbo+complemento (max 80 caracteres)",
      "impacto": "string que explica el beneficio esperado (max 150 caracteres)",
      "emoji": "1 emoji representativo"
    }
  ],
  "felicitaciones": "string corto opcional (max 150 caracteres) si hay algo realmente bien hecho, null si no aplica"
}

Reglas estrictas :
- 3 a 5 highlights : una mezcla equilibrada de fortalezas y riesgos
- 2 a 4 recomendaciones : siempre con verbo de acción al inicio
- Usa números concretos del análisis (no «mejora algo» sino «sube tu recurrencia del X% al 60%»)
- No inventes datos que no están en el input
- Si el autónomo está en una situación crítica, sé honesto pero constructivo
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function userPrompt(array $input): string
    {
        $json = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return "Aquí están los chiffres de mi actividad de autónomo para el año en curso. Analízame y dame tu veredicto en el formato JSON acordado.\n\n```json\n{$json}\n```";
    }
}
