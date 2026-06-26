<?php

namespace App\Support;

use App\Enums\ChargeFrequency;
use Carbon\CarbonImmutable;

/**
 * Calcule la prochaine échéance d'une charge récurrente ancrée
 * sur un jour du mois donné.
 */
final class NextDueDateCalculator
{
    public function compute(ChargeFrequency $frequency, int $dayOfMonth, CarbonImmutable $from): CarbonImmutable
    {
        $dayOfMonth = max(1, min(31, $dayOfMonth));

        return match ($frequency) {
            ChargeFrequency::Weekly => $from->addDays(7),
            ChargeFrequency::Monthly => $this->nextMonthlyOnOrAfter($from, $dayOfMonth),
            ChargeFrequency::Quarterly => $this->nextEveryNMonthsOnOrAfter($from, $dayOfMonth, 3),
            ChargeFrequency::Yearly => $this->nextEveryNMonthsOnOrAfter($from, $dayOfMonth, 12),
        };
    }

    private function nextMonthlyOnOrAfter(CarbonImmutable $from, int $day): CarbonImmutable
    {
        $candidate = $this->setDayClamped($from, $day);

        return $candidate->greaterThanOrEqualTo($from)
            ? $candidate
            : $this->setDayClamped($from->addMonthNoOverflow(), $day);
    }

    private function nextEveryNMonthsOnOrAfter(CarbonImmutable $from, int $day, int $months): CarbonImmutable
    {
        $candidate = $this->setDayClamped($from, $day);

        while ($candidate->lessThan($from)) {
            $candidate = $this->setDayClamped($candidate->addMonthsNoOverflow($months), $day);
        }

        return $candidate;
    }

    private function setDayClamped(CarbonImmutable $date, int $day): CarbonImmutable
    {
        return $date->day(min($day, $date->daysInMonth));
    }
}
