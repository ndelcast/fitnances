<?php

namespace App\Http\Controllers;

use App\Services\CashForecastService;
use App\Services\TreasuryService;
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
        ]);
    }

    private function euros(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
