<?php

namespace App\DTOs;

/**
 * Photographie de trésorerie d'un freelance (montants en centimes).
 *
 * - cash : solde réel (encaissements - charges).
 * - provisions : ce qui est dû à l'État (URSSAF/TVA/IR).
 * - available : réellement disponible (cash - provisions).
 * - withdrawableThisMonth : ce qu'on peut se verser ce mois-ci,
 *   une fois les charges récurrentes restantes du mois couvertes.
 */
final readonly class TreasurySnapshot
{
    public function __construct(
        public int $cash,
        public ProvisionBreakdown $provisions,
        public int $available,
        public int $withdrawableThisMonth,
    ) {}
}
