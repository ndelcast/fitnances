<?php

namespace App\DTOs;

/**
 * Estimation annuelle de la Renta (IRPF réel selon barème progressif)
 * et de la provision mensuelle nécessaire en plus du Modelo 130 déjà
 * versé. Montants en centimes.
 */
final readonly class RentaProvision
{
    public function __construct(
        public int $rendimientoNetoAnnual,
        public int $baseImponible,
        public int $rentaIrpf,
        public float $marginalRate,
        public int $modelo130Annual,
        public int $retentionsAnnual,
        public int $restanteRenta,
        public int $monthlyProvision,
    ) {}
}
