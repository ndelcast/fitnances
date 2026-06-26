<?php

namespace App\Http\Controllers;

use App\Services\CashForecastService;
use App\Services\TreasuryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TreasuryService $treasury, CashForecastService $forecastService): Response
    {
        $user = $request->user();

        $snapshot = $treasury->snapshot($user);
        $forecast = $forecastService->forUser($user, 90);

        return Inertia::render('Dashboard', [
            'user' => ['name' => $user->name],
            'headline' => $this->euros($snapshot->withdrawableThisMonth),
            'cash' => $this->euros($snapshot->cash),
            'available' => $this->euros($snapshot->available),
            'provisions' => [
                'iva' => $this->euros($snapshot->provisions->iva),
                'irpf' => $this->euros($snapshot->provisions->irpf),
                'total' => $this->euros($snapshot->provisions->total()),
            ],
            'forecast' => [
                'startingCash' => $this->euros($forecast->startingCash),
                'expectedIncome' => $this->euros($forecast->expectedIncome),
                'projectedCharges' => $this->euros($forecast->projectedCharges),
                'projectedBalance' => $this->euros($forecast->projectedBalance()),
                'from' => $forecast->from->toDateString(),
                'to' => $forecast->to->toDateString(),
            ],
            'upcomingDeadlines' => $this->upcomingDeadlines($user, $snapshot),
        ]);
    }

    private function upcomingDeadlines($user, $snapshot): array
    {
        $today = CarbonImmutable::today();
        $deadlines = [];

        $taxDeadline = $this->nextQuarterlyDeadline($today);

        $deadlines[] = [
            'modelo' => 'Modelo 303',
            'label' => 'IVA trimestral',
            'date' => $taxDeadline->toDateString(),
            'daysLeft' => $today->diffInDays($taxDeadline, false),
            'amount' => $this->euros($snapshot->provisions->iva),
        ];

        $deadlines[] = [
            'modelo' => 'Modelo 130',
            'label' => 'Pago fraccionado IRPF',
            'date' => $taxDeadline->toDateString(),
            'daysLeft' => $today->diffInDays($taxDeadline, false),
            'amount' => $this->euros($snapshot->provisions->irpf),
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
     * Modelo 303/130 sont déclarés du 1er au 20 du mois suivant la fin de trimestre,
     * sauf le 4e trimestre (jusqu'au 30 janvier).
     */
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
}
