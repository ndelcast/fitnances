<?php

namespace App\DTOs;

use Carbon\CarbonInterface;

/**
 * Prévisionnel de trésorerie sur une fenêtre (montants en centimes).
 */
final readonly class CashForecast
{
    public function __construct(
        public int $startingCash,
        public int $expectedIncome,
        public int $projectedCharges,
        public CarbonInterface $from,
        public CarbonInterface $to,
    ) {}

    public function projectedBalance(): int
    {
        return $this->startingCash + $this->expectedIncome - $this->projectedCharges;
    }
}
