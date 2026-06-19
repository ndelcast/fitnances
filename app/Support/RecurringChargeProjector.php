<?php

namespace App\Support;

use App\Models\RecurringCharge;
use Carbon\CarbonInterface;

/**
 * Projette les occurrences d'une charge récurrente sur une fenêtre temporelle.
 */
final class RecurringChargeProjector
{
    /**
     * Montant total (en centimes) dû par cette charge entre $from et $to inclus.
     */
    public function totalBetween(RecurringCharge $charge, CarbonInterface $from, CarbonInterface $to): int
    {
        return count($this->occurrencesBetween($charge, $from, $to)) * $charge->amount;
    }

    /**
     * Dates d'échéance tombant dans la fenêtre [$from, $to].
     *
     * @return array<int, CarbonInterface>
     */
    public function occurrencesBetween(RecurringCharge $charge, CarbonInterface $from, CarbonInterface $to): array
    {
        $occurrences = [];
        $date = $charge->next_due_on->copy();

        // Avance jusqu'à entrer dans la fenêtre.
        while ($date->lessThan($from)) {
            $date = $charge->frequency->next($date);
        }

        // Collecte tant qu'on reste dans la fenêtre.
        while ($date->lessThanOrEqualTo($to)) {
            $occurrences[] = $date->copy();
            $date = $charge->frequency->next($date);
        }

        return $occurrences;
    }
}
