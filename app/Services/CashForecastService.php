<?php

namespace App\Services;

use App\DTOs\CashForecast;
use App\Enums\MovementKind;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Construit le prévisionnel de trésorerie : solde réel + ingresos prévus
 * - dépenses prévues sur une fenêtre.
 */
final class CashForecastService
{
    public function forUser(User $user, int $days = 90, ?CarbonInterface $from = null): CashForecast
    {
        $from = $from ? CarbonImmutable::instance($from) : CarbonImmutable::today();
        $to = $from->addDays($days);

        $startingCash = $this->currentCash($user);

        $expectedIncome = (int) $user->movements()
            ->unpaid()
            ->ofKind(MovementKind::Income)
            ->inWindow($from, $to)
            ->sum('amount');

        $projectedCharges = (int) $user->movements()
            ->unpaid()
            ->ofKind(MovementKind::Expense, MovementKind::Salary, MovementKind::Tax)
            ->inWindow($from, $to)
            ->sum('amount');

        return new CashForecast($startingCash, $expectedIncome, $projectedCharges, $from, $to);
    }

    /**
     * Solde réel : seuls les movements payés (paid_at != null) entrent
     * dans le calcul. Les income augmentent le solde, les autres kinds
     * (expense, salary, tax) le réduisent.
     */
    public function currentCash(User $user): int
    {
        $income = (int) $user->movements()
            ->paid()
            ->ofKind(MovementKind::Income)
            ->sum('amount');

        $outflow = (int) $user->movements()
            ->paid()
            ->ofKind(MovementKind::Expense, MovementKind::Salary, MovementKind::Tax)
            ->sum('amount');

        return $income - $outflow;
    }
}
