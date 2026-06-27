<?php

namespace App\DTOs;

/**
 * Indicateurs de santé commerciale (côté chiffre d'affaires).
 *
 *  - recurrenciaPct : % du chiffre annuel venant de movements récurrents
 *    (= souscriptions / clients mensuels) vs ponctuels.
 *  - avgDsoDays     : délai moyen d'encaissement (paid_at − issued_on)
 *    sur les factures payées de l'année avec issued_on renseigné.
 *  - pendientesCount / pendientesAmount : factures non payées dont la
 *    date d'émission est passée (autrement dit : retard ou en cours).
 */
final readonly class SalesHealth
{
    public function __construct(
        public int $recurrenciaPct,        // 0-100
        public int $recurrenciaAmount,     // cents annuels récurrents
        public int $totalAnnualIncome,     // cents annuels total
        public int $recurrenciaScore,      // 0-100 = recurrenciaPct (plus haut = mieux)

        public ?int $avgDsoDays,           // jours, null si pas de données
        public int $dsoSampleSize,         // nb factures dans le calcul

        public int $pendientesCount,
        public int $pendientesAmount,      // cents
    ) {}
}
