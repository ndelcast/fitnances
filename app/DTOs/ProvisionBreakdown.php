<?php

namespace App\DTOs;

/**
 * Montants (en centimes) à mettre de côté sur les encaissements.
 */
final readonly class ProvisionBreakdown
{
    public function __construct(
        public int $vat,
        public int $urssaf,
        public int $incomeTax,
    ) {}

    public function total(): int
    {
        return $this->vat + $this->urssaf + $this->incomeTax;
    }
}
