<?php

namespace App\Services;

use App\DTOs\BusinessHealth;
use App\Enums\MovementKind;
use App\Models\User;

/**
 * Calcule 3 indicateurs intuitifs pour un autónomo :
 *
 *  1. Sueldo sostenible (€/mes)
 *     Salaire maximal que la trésorerie peut absorber mois après mois,
 *     échéances Hacienda incluses (cf. SustainableSalaryService).
 *     Score = sustainable / current_salary × 100 (capé 100).
 *
 *  2. Colchón de caja (meses)
 *     = (cash réel libre fin d'année − Q4) / burn mensuel
 *     Cushion = ce qu'il reste de cash au 31 dic après avoir versé le
 *     salaire toute l'année, payé Q1-Q3 et mis Q4 de côté pour janvier.
 *     Si l'utilisateur n'a pas de Salario en grille, on prend le
 *     sustainable comme salaire implicite (donc cushion = 0 par def).
 *     Score = runway / 6 mois × 100 (capé 100).
 *
 *  3. Recurrencia (%)
 *     Part du chiffre annuel qui vient de revenus récurrents (lignes
 *     mensuelles de la grille, source = Recurring). Plus la part est
 *     élevée, plus tu peux prévoir et lisser ton activité.
 *     Score = recurrenciaPct directement (0-100).
 */
final class BusinessHealthService
{
    public function __construct(
        private readonly SustainableSalaryService $sustainable,
        private readonly SalesHealthService $sales,
    ) {}

    public function forUser(User $user, int $year): BusinessHealth
    {
        $profile = $user->financialProfile;
        $salaryFromProfile = $profile?->monthly_salary ?? 0;
        $annualSalary = (int) $user->movements()
            ->whereYear('estimated_on', $year)
            ->where('kind', MovementKind::Salary->value)
            ->sum('amount');
        $observedSalary = (int) round($annualSalary / 12);
        // Le cashflow observé prime sur le profil onboarding : si l'utilisateur
        // a modifié son Salario dans la grille, on reflète sa réalité.
        $currentSalary = $observedSalary > 0 ? $observedSalary : $salaryFromProfile;

        // === 1. Sueldo sostenible ===
        // On démarre la sim à 0 € (sans le capital initial) pour que le
        // sustainable ne tape PAS dans le colchón préexistant : l'activité
        // doit s'autofinancer. Sinon on conseillerait de cramer l'épargne.
        $plan = $user->cashFlowPlans()->where('year', $year)->first();
        $startingCash = (int) ($plan?->starting_balance ?? 0);
        $simulation = $this->sustainable->forYear($user, $year, 0);
        $sustainableSalary = $simulation->sustainableSalary;
        $sueldoScore = $currentSalary > 0
            ? (int) max(0, min(100, round($sustainableSalary / $currentSalary * 100)))
            : 100;

        // === 2. Colchón de caja ===
        // Ici on prend le startingCash réel : le capital initial fait
        // bien partie du cash disponible pour le runway.
        $effectiveSalary = $currentSalary > 0 ? $currentSalary : $sustainableSalary;
        $currentSim = $this->sustainable->simulateAt($user, $year, $effectiveSalary, $startingCash);
        $cushion = max(0, $currentSim->yearEndAfterQ4);
        $annualExpense = (int) $user->movements()
            ->whereYear('estimated_on', $year)
            ->where('kind', MovementKind::Expense->value)
            ->sum('amount');
        $monthlyBurn = (int) round(($annualExpense + 12 * $effectiveSalary) / 12);
        $runwayMonths = $monthlyBurn > 0 ? $cushion / $monthlyBurn : 0;
        $colchonScore = (int) max(0, min(100, round($runwayMonths / 6 * 100)));

        // === 3. Recurrencia ===
        $sales = $this->sales->forYear($user, $year);
        $recurrenciaScore = $sales->recurrenciaScore;

        // Score global = moyenne arithmétique des 3 indicateurs.
        $globalScore = (int) round(($sueldoScore + $colchonScore + $recurrenciaScore) / 3);
        [$statusLabel, $statusSeverity] = $this->statusFor($globalScore);

        return new BusinessHealth(
            sustainableSalary: $sustainableSalary,
            currentSalary: $currentSalary,
            sueldoScore: $sueldoScore,
            sueldoBottleneckMonth: $simulation->bottleneckMonth,
            runwayMonths: $runwayMonths,
            colchonScore: $colchonScore,
            recurrenciaPct: $sales->recurrenciaPct,
            recurrenciaScore: $recurrenciaScore,
            globalScore: $globalScore,
            statusLabel: $statusLabel,
            statusSeverity: $statusSeverity,
        );
    }

    /**
     * @return array{0:string,1:string} [label, severity]
     */
    private function statusFor(int $score): array
    {
        return match (true) {
            $score >= 75 => ['Excelente', 'success'],
            $score >= 55 => ['Buena salud', 'success'],
            $score >= 35 => ['Atención', 'warn'],
            default => ['Crítico', 'danger'],
        };
    }
}
