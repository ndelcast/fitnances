<?php

namespace App\DTOs;

/**
 * Photographie de trésorerie d'un autónomo (montants en centimes).
 *
 * - cash : solde réel (encaissements - charges).
 * - provisions : ce qui est dû à Hacienda (IVA / IRPF).
 * - available : réellement disponible (cash - provisions).
 * - withdrawableThisMonth : ce qu'on peut se verser ce mois-ci,
 *   une fois les charges récurrentes restantes du mois couvertes
 *   (cuota d'autónomos comprise).
 */
final readonly class TreasurySnapshot
{
    public function __construct(
        public int $cash,
        public TaxProvisions $provisions,
        public int $available,
        public int $withdrawableThisMonth,
    ) {}
}
