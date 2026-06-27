<?php

namespace App\DTOs;

/**
 * Indicateurs de santé du business autónomo, version intuitive :
 *
 *  - sueldo sostenible : ce que l'activité peut vraiment te payer chaque mois
 *  - colchón de caja   : combien de mois tu peux tenir sans cobrar
 *  - recurrencia       : % du chiffre venant de revenus prévisibles
 *
 * Chaque indicateur a une valeur "concrète" et un score 0-100 utilisé
 * pour la couleur du ring.
 */
final readonly class BusinessHealth
{
    public function __construct(
        // Sueldo sostenible
        public int $sustainableSalary,   // cents/mois
        public int $currentSalary,       // cents/mois (observé sur le cashflow ou profil en fallback)
        public int $sueldoScore,         // 0-100
        public ?int $sueldoBottleneckMonth, // 1..12, mois où la caisse touche son min (null si vide)

        // Colchón de caja
        public float $runwayMonths,      // décimales
        public int $colchonScore,        // 0-100

        // Recurrencia (proxy de prévisibilité du chiffre)
        public int $recurrenciaPct,      // 0-100, % chiffre récurrent
        public int $recurrenciaScore,    // 0-100, identique à recurrenciaPct (plus haut = mieux)

        // Global
        public int $globalScore,
        public string $statusLabel,
        public string $statusSeverity,
    ) {}
}
