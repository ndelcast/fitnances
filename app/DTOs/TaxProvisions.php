<?php

namespace App\DTOs;

/**
 * Provisions fiscales (en centimes) d'un autónomo : ce qu'il faut mettre
 * de côté pour payer IVA (Modelo 303) et IRPF (Modelo 130).
 *
 * La cuota mensuelle d'autónomos n'est pas incluse : elle est déjà
 * comptabilisée comme charge récurrente.
 */
final readonly class TaxProvisions
{
    public function __construct(
        public int $iva,
        public int $irpf,
    ) {}

    public function total(): int
    {
        return $this->iva + $this->irpf;
    }
}
