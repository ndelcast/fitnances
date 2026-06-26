<?php

namespace App\Services;

use App\DTOs\CashForecast;
use App\Enums\TransactionType;
use App\Models\User;
use App\Support\RecurringChargeProjector;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Construit le prévisionnel de trésorerie d'un freelance :
 * solde actuel + factures à venir - charges récurrentes, sur une fenêtre.
 */
final class CashForecastService
{
    public function __construct(
        private readonly RecurringChargeProjector $projector,
    ) {}

    public function forUser(User $user, int $days = 90, ?CarbonInterface $from = null): CashForecast
    {
        $from = $from ? $from->copy() : CarbonImmutable::today();
        $to = $from->copy()->addDays($days);

        $startingCash = $this->currentCash($user);

        $expectedIncome = (int) $user->expectedIncomes()
            ->pending()
            ->whereBetween('expected_on', [$from, $to])
            ->sum('amount');

        $projectedCharges = $user->recurringCharges()
            ->active()
            ->get()
            ->sum(fn ($charge) => $this->projector->totalBetween($charge, $from, $to));

        return new CashForecast($startingCash, $expectedIncome, (int) $projectedCharges, $from, $to);
    }

    /**
     * Solde réel : seulement ce qui est effectivement cobrado/pagado
     * (paid_at non null). Les transactions encore previstas n'entrent pas
     * dans le cash — elles alimentent le forecast.
     */
    public function currentCash(User $user): int
    {
        $income = (int) $user->transactions()
            ->where('type', TransactionType::Income)
            ->whereNotNull('paid_at')
            ->sum('amount');
        $expense = (int) $user->transactions()
            ->where('type', TransactionType::Expense)
            ->whereNotNull('paid_at')
            ->sum('amount');

        return $income - $expense;
    }
}
