<?php

namespace App\Services;

use App\DTOs\TreasurySnapshot;
use App\Enums\MovementKind;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Orchestre les calculs de trésorerie pour produire la photographie
 * d'un autónomo : « este mes puedes retirar X € ».
 */
final class TreasuryService
{
    public function __construct(
        private readonly ProvisioningService $provisioning,
        private readonly CashForecastService $forecast,
    ) {}

    public function snapshot(User $user): TreasurySnapshot
    {
        $profile = $user->financialProfile ?? $user->financialProfile()->make();

        $cash = $this->forecast->currentCash($user);

        // Provisions calculées sur les movements payés uniquement
        // (on apparte ce qui correspond au cash réellement présent).
        $income = $user->movements()->paid()->ofKind(MovementKind::Income)->get();
        $expense = $user->movements()->paid()->ofKind(MovementKind::Expense)->get();
        $provisions = $this->provisioning->forPeriod($income, $expense, $profile);

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
     * Charges récurrentes restantes (expense + salary + tax non payés)
     * à payer d'ici la fin du mois.
     */
    private function remainingChargesThisMonth(User $user): int
    {
        $from = CarbonImmutable::today();
        $to = $from->endOfMonth();

        return (int) $user->movements()
            ->unpaid()
            ->ofKind(MovementKind::Expense, MovementKind::Salary, MovementKind::Tax)
            ->inWindow($from, $to)
            ->sum('amount');
    }
}
