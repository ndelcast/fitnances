<?php

namespace App\Services;

use App\DTOs\ProvisionBreakdown;
use App\DTOs\TreasurySnapshot;
use App\Models\User;
use App\Support\RecurringChargeProjector;
use Carbon\CarbonImmutable;

/**
 * Orchestre les calculs de trésorerie pour produire la photographie
 * d'un freelance, dont le chiffre unique : « tu peux te verser X€ ce mois-ci ».
 */
final class TreasuryService
{
    public function __construct(
        private readonly ProvisioningService $provisioning,
        private readonly CashForecastService $forecast,
        private readonly RecurringChargeProjector $projector,
    ) {}

    public function snapshot(User $user): TreasurySnapshot
    {
        $profile = $user->financialProfile ?? $user->financialProfile()->make();

        $cash = $this->forecast->currentCash($user);

        $income = $user->transactions()->income()->get();
        $provisions = $this->provisioning->forIncome($income, $profile);

        $available = $cash - $provisions->total();

        $withdrawable = $available - $this->remainingChargesThisMonth($user);

        return new TreasurySnapshot(
            cash: $cash,
            provisions: $provisions,
            available: $available,
            withdrawableThisMonth: $withdrawable,
        );
    }

    /**
     * Charges récurrentes restant à payer d'ici la fin du mois (en centimes).
     */
    private function remainingChargesThisMonth(User $user): int
    {
        $from = CarbonImmutable::today();
        $to = $from->endOfMonth();

        return (int) $user->recurringCharges()
            ->active()
            ->get()
            ->sum(fn ($charge) => $this->projector->totalBetween($charge, $from, $to));
    }
}
