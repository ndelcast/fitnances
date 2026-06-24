<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum ChargeFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Hebdomadaire',
            self::Monthly => 'Mensuelle',
            self::Quarterly => 'Trimestrielle',
            self::Yearly => 'Annuelle',
        };
    }

    /**
     * Retourne la prochaine échéance après la date donnée.
     */
    public function next(CarbonInterface $from): CarbonInterface
    {
        return match ($this) {
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonthNoOverflow(),
            self::Quarterly => $from->copy()->addMonthsNoOverflow(3),
            self::Yearly => $from->copy()->addYear(),
        };
    }
}
