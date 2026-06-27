<?php

namespace App\DTOs;

/**
 * Résultat de la simulation de salaire soutenable : le salaire mensuel
 * maximal que la trésorerie peut absorber sur 12 mois, sans jamais
 * descendre sous le seuil exigé, ainsi que la trajectoire de caisse
 * mois par mois utilisée pour le calcul.
 *
 * `trajectory[m]` = solde de caisse projeté à la fin du mois m
 * (en centimes, m ∈ 1..12).
 */
final readonly class SalarySimulation
{
    /**
     * @param  array<int,int>  $trajectory
     */
    public function __construct(
        public int $sustainableSalary,   // cents / mois
        public int $startingCash,        // cents
        public int $safetyFloor,         // cents (cash plancher exigé)
        public array $trajectory,
        public int $minBalance,          // plus bas du solde sur la fenêtre (Q4 inclus)
        public ?int $bottleneckMonth,    // 1..12, mois où le min est atteint (null = vide)
        public int $yearEndAfterQ4,      // balance au 31 dic − obligation Q4 (= cash réellement libre)
    ) {}
}
