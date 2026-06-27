<?php

namespace App\Http\Controllers;

use App\Enums\MovementKind;
use App\Services\BusinessHealthService;
use App\Services\CashForecastService;
use App\Services\ProvisioningService;
use App\Services\RentaProvisionService;
use App\Services\SalesHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CashForecastService $forecastService,
        RentaProvisionService $rentaService,
        BusinessHealthService $healthService,
        SalesHealthService $salesService,
        ProvisioningService $provisioningService,
    ): Response {
        $user = $request->user();
        $today = CarbonImmutable::today();

        $forecast = $forecastService->forUser($user, 90);
        $renta = $rentaService->forYear($user, $today->year);
        $health = $healthService->forUser($user, $today->year);
        $sales = $salesService->forYear($user, $today->year);

        // Provision Renta accumulée à ce jour : 1/12 × mois écoulés.
        $rentaAccruedYtd = (int) round($renta->restanteRenta * $today->month / 12);

        // Provisions pour la prochaine déclaration trimestrielle.
        $quarter = $this->currentOpenQuarter($today);
        $quarterProvisions = $this->provisionsForQuarter($user, $provisioningService, $quarter, $today);
        $totalProvisioned = $quarterProvisions->total() + $rentaAccruedYtd;

        return Inertia::render('Dashboard', [
            'user' => ['name' => $user->name],
            'money' => [
                'provisionsTotal' => $this->euros($totalProvisioned),
            ],
            'provisions' => [
                'quarterLabel' => 'Q'.$quarter['q'].' '.$quarter['year'],
                'quarterDue' => $quarter['due'],
                'iva' => [
                    'label' => 'IVA Modelo 303 · Q'.$quarter['q'],
                    'amount' => $this->euros($quarterProvisions->iva),
                ],
                'irpfModelo130' => [
                    'label' => 'IRPF Modelo 130 · Q'.$quarter['q'],
                    'amount' => $this->euros($quarterProvisions->irpf),
                ],
                'rentaAccrued' => [
                    'label' => 'Renta '.$today->year.' (provisión acumulada)',
                    'amount' => $this->euros($rentaAccruedYtd),
                    'annual' => $this->euros($renta->restanteRenta),
                    'marginalRate' => round($renta->marginalRate * 100),
                ],
            ],
            'health' => [
                'globalScore' => $health->globalScore,
                'statusLabel' => $health->statusLabel,
                'statusSeverity' => $health->statusSeverity,
                'metrics' => [
                    [
                        'key' => 'sueldo',
                        'label' => 'Sueldo sostenible',
                        'score' => $health->sueldoScore,
                        'centerText' => $this->formatEurosFloor($health->sustainableSalary).' €/mes',
                        'subline' => 'Te pagas '.$this->formatEurosFloor($health->currentSalary).' €/mes',
                        'hint' => 'Sueldo máximo que tu caja puede asumir mes a mes este año, contando IVA y Modelo 130 trimestrales y dejando aparte el Q4. Cifra redondeada hacia abajo: si te pagas ese importe exacto, no entras en negativo.',
                        'color' => $this->colorFor($health->sueldoScore),
                    ],
                    [
                        'key' => 'colchon',
                        'label' => 'Colchón de caja',
                        'score' => $health->colchonScore,
                        'centerText' => $this->formatRunway($health->runwayMonths),
                        'subline' => 'libres a fin de año',
                        'hint' => 'Meses de actividad que tu cash sobrante (después de salario y Hacienda) podría cubrir. Apunta a 3-6 meses como fondo de emergencia: bájate un poco el sueldo y verás crecer este colchón mes a mes.',
                        'color' => $this->colorFor($health->colchonScore),
                    ],
                    [
                        'key' => 'recurrencia',
                        'label' => 'Recurrencia',
                        'score' => $health->recurrenciaScore,
                        'centerText' => $health->recurrenciaPct.' %',
                        'subline' => $health->recurrenciaPct > 0
                            ? 'del chiffre es predecible'
                            : 'Aún no hay ingresos este año',
                        'hint' => 'Parte de tu chiffre que viene de clientes recurrentes (mensualidades, abonos) y no de facturas puntuales. Cuanto más alto, más previsible es tu mes a mes. Objetivo: ≥60 % en actividad estable.',
                        'color' => $this->colorFor($health->recurrenciaScore),
                    ],
                ],
            ],
            'renta' => [
                'year' => $today->year,
                'rendimientoNeto' => $this->euros($renta->rendimientoNetoAnnual),
                'baseImponible' => $this->euros($renta->baseImponible),
                'rentaIrpf' => $this->euros($renta->rentaIrpf),
                'marginalRate' => round($renta->marginalRate * 100),
                'modelo130Annual' => $this->euros($renta->modelo130Annual),
                'retentionsAnnual' => $this->euros($renta->retentionsAnnual),
                'restanteRenta' => $this->euros($renta->restanteRenta),
                'monthlyProvision' => $this->euros($renta->monthlyProvision),
            ],
            'forecast' => [
                'startingCash' => $this->euros($forecast->startingCash),
                'expectedIncome' => $this->euros($forecast->expectedIncome),
                'projectedCharges' => $this->euros($forecast->projectedCharges),
                'projectedBalance' => $this->euros($forecast->projectedBalance()),
                'from' => $forecast->from->toDateString(),
                'to' => $forecast->to->toDateString(),
            ],
            'sales' => [
                'recurrenciaPct' => $sales->recurrenciaPct,
                'recurrenciaAmount' => $this->euros($sales->recurrenciaAmount),
                'totalAnnualIncome' => $this->euros($sales->totalAnnualIncome),
                'avgDsoDays' => $sales->avgDsoDays,
                'dsoSampleSize' => $sales->dsoSampleSize,
                'pendientesCount' => $sales->pendientesCount,
                'pendientesAmount' => $this->euros($sales->pendientesAmount),
            ],
            'upcomingDeadlines' => $this->upcomingDeadlines($user, $quarterProvisions),
        ]);
    }

    private function upcomingDeadlines($user, \App\DTOs\TaxProvisions $quarterProvisions): array
    {
        $today = CarbonImmutable::today();
        $deadlines = [];

        $taxDeadline = $this->nextQuarterlyDeadline($today);

        $deadlines[] = [
            'modelo' => 'Modelo 303',
            'label' => 'IVA trimestral',
            'date' => $taxDeadline->toDateString(),
            'daysLeft' => $today->diffInDays($taxDeadline, false),
            'amount' => $this->euros($quarterProvisions->iva),
        ];

        $deadlines[] = [
            'modelo' => 'Modelo 130',
            'label' => 'Pago fraccionado IRPF',
            'date' => $taxDeadline->toDateString(),
            'daysLeft' => $today->diffInDays($taxDeadline, false),
            'amount' => $this->euros($quarterProvisions->irpf),
        ];

        $cuota = $user->movements()
            ->unpaid()
            ->where('label', 'Cuota autónomos')
            ->where('estimated_on', '>=', $today->toDateString())
            ->orderBy('estimated_on')
            ->first();

        if ($cuota) {
            $deadlines[] = [
                'modelo' => 'Cuota autónomos',
                'label' => 'Domiciliación mensual',
                'date' => $cuota->estimated_on->toDateString(),
                'daysLeft' => $today->diffInDays($cuota->estimated_on, false),
                'amount' => $this->euros($cuota->amount),
            ];
        }

        usort($deadlines, fn ($a, $b) => $a['daysLeft'] <=> $b['daysLeft']);

        return $deadlines;
    }

    /**
     * Identifie le trimestre dont l'échéance Modelo 303/130 est la plus
     * proche dans le futur (ou aujourd'hui). Renvoie aussi sa fenêtre
     * de cobro et sa date d'échéance.
     *
     * @return array{q:int, year:int, from:string, to:string, due:string}
     */
    private function currentOpenQuarter(CarbonImmutable $today): array
    {
        $candidates = [
            ['q' => 1, 'year' => $today->year, 'from' => "{$today->year}-01-01", 'to' => "{$today->year}-03-31", 'due' => "{$today->year}-04-20"],
            ['q' => 2, 'year' => $today->year, 'from' => "{$today->year}-04-01", 'to' => "{$today->year}-06-30", 'due' => "{$today->year}-07-20"],
            ['q' => 3, 'year' => $today->year, 'from' => "{$today->year}-07-01", 'to' => "{$today->year}-09-30", 'due' => "{$today->year}-10-20"],
            ['q' => 4, 'year' => $today->year, 'from' => "{$today->year}-10-01", 'to' => "{$today->year}-12-31", 'due' => ($today->year + 1).'-01-30'],
        ];

        foreach ($candidates as $c) {
            if ($today->lessThanOrEqualTo(CarbonImmutable::parse($c['due']))) {
                return $c;
            }
        }

        // Toutes les échéances de l'année sont passées → Q1 de l'année suivante.
        $nextYear = $today->year + 1;

        return [
            'q' => 1, 'year' => $nextYear,
            'from' => "{$nextYear}-01-01", 'to' => "{$nextYear}-03-31",
            'due' => "{$nextYear}-04-20",
        ];
    }

    /**
     * @param  array{q:int, year:int, from:string, to:string, due:string}  $quarter
     */
    private function provisionsForQuarter($user, ProvisioningService $provisioning, array $quarter, CarbonImmutable $today): \App\DTOs\TaxProvisions
    {
        $profile = $user->financialProfile ?? $user->financialProfile()->make();
        $from = CarbonImmutable::parse($quarter['from'])->startOfDay();
        $quarterEnd = CarbonImmutable::parse($quarter['to'])->endOfDay();
        // On plafonne au plus tôt entre la fin de trimestre et aujourd'hui :
        // tant que le trim est en cours, on ne compte que ce qui est cobrado.
        $to = $today->lessThan($quarterEnd) ? $today->endOfDay() : $quarterEnd;

        $incomes = $user->movements()
            ->paid()
            ->ofKind(MovementKind::Income)
            ->whereBetween('paid_at', [$from, $to])
            ->get();
        $expenses = $user->movements()
            ->paid()
            ->ofKind(MovementKind::Expense)
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        return $provisioning->forPeriod($incomes, $expenses, $profile);
    }

    private function nextQuarterlyDeadline(CarbonImmutable $today): CarbonImmutable
    {
        $candidates = [
            CarbonImmutable::create($today->year, 4, 20),
            CarbonImmutable::create($today->year, 7, 20),
            CarbonImmutable::create($today->year, 10, 20),
            CarbonImmutable::create($today->year + 1, 1, 30),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate->greaterThanOrEqualTo($today)) {
                return $candidate;
            }
        }

        return $candidates[0]->addYear();
    }

    private function euros(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function formatEurosShort(int $cents): string
    {
        $euros = $cents / 100;
        if ($euros >= 1000) {
            return number_format($euros / 1000, 1, ',', '').'k';
        }

        return number_format($euros, 0, ',', '');
    }

    /**
     * Affiche le montant en euros entiers, arrondi à l'inférieur :
     * 201619 cents (2 016,19 €) → "2 016". Utile pour les valeurs où
     * il vaut mieux ne pas suggérer plus que ce que la caisse supporte.
     */
    private function formatEurosFloor(int $cents): string
    {
        $euros = intdiv($cents, 100);

        return number_format($euros, 0, ',', ' ');
    }

    private function colorFor(int $score): string
    {
        return match (true) {
            $score >= 75 => 'emerald',
            $score >= 50 => 'amber',
            default => 'red',
        };
    }

    private function formatRunway(float $months): string
    {
        if ($months >= 12) {
            return '12+ meses';
        }
        if ($months >= 1) {
            return round($months, 1).' meses';
        }
        $weeks = (int) round($months * 4);

        return $weeks.' sem.';
    }
}
