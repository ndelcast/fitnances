<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CashForecastService;
use App\Services\TreasuryService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(TreasuryService $treasury, CashForecastService $forecastService): Response
    {
        // TODO(auth) : remplacer par auth()->user() une fois l'authentification en place.
        $user = User::query()->firstOrFail();

        $snapshot = $treasury->snapshot($user);
        $forecast = $forecastService->forUser($user, 90);

        return Inertia::render('Dashboard', [
            'user' => ['name' => $user->name],
            'headline' => $this->euros($snapshot->withdrawableThisMonth),
            'cash' => $this->euros($snapshot->cash),
            'available' => $this->euros($snapshot->available),
            'provisions' => [
                'total' => $this->euros($snapshot->provisions->total()),
                'vat' => $this->euros($snapshot->provisions->vat),
                'urssaf' => $this->euros($snapshot->provisions->urssaf),
                'incomeTax' => $this->euros($snapshot->provisions->incomeTax),
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
