<?php

namespace App\Services;

use App\DTOs\SalarySimulation;
use App\Enums\MovementKind;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Calcule le salaire mensuel maximal qu'un autónomo peut s'attribuer
 * sans faire passer le "Balance acumulado" sous un seuil donné, en
 * simulant la trésorerie mois par mois sur l'année.
 *
 * La formule de simulation est identique à celle affichée dans
 * /flujo-caja, pour que le résultat soit visuellement traçable dans
 * la grille :
 *
 *   monthlyBalance[m] = ingresos[m] − gastos[m] − salaire
 *                       − (IVA + Modelo 130 dûs ce mois-là)
 *                       − (restanteRenta / 12)
 *   cumulative[m]     = cumulative[m-1] + monthlyBalance[m]
 *
 * Distinction obligation / paiement :
 *  - L'obligation fiscale est calculée par trimestre et "earmarkée"
 *    fin de trimestre. Elle ne quitte pas le cash à ce moment.
 *  - Le paiement effectif tombe le 1ᵉʳ mois du trimestre suivant
 *    (Q1 → avril, Q2 → juillet, Q3 → octobre, Q4 → janvier N+1).
 *  - La sim impute les cash-out de Q1, Q2, Q3 dans l'année. Q4 est
 *    payé hors fenêtre : on exige donc que le balance de décembre
 *    couvre l'obligation Q4 pour ne pas être à découvert en janvier.
 *
 * Les Movements de kind Salary et Tax existants sont ignorés : le
 * salaire candidat les remplace et les taxes sont recalculées.
 */
final class SustainableSalaryService
{
    public function __construct(
        private readonly ProvisioningService $provisioning,
        private readonly RentaProvisionService $renta,
    ) {}

    public function forYear(User $user, int $year, int $startingCash = 0, int $safetyFloor = 0): SalarySimulation
    {
        $monthly = $this->monthlyAggregates($user, $year);
        $quarterly = $this->quarterlyTaxes($user, $year);
        $rentaMonthly = $this->rentaMonthlyAccrual($user, $year);

        $upper = $this->upperBound($monthly);

        $low = 0;
        $high = $upper;
        while ($high - $low > 1) { // précision = 1 cent
            $mid = intdiv($low + $high, 2);
            $sim = $this->simulate($monthly, $quarterly, $rentaMonthly, $startingCash, $safetyFloor, $mid);
            if ($sim['isOk']) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        $final = $this->simulate($monthly, $quarterly, $rentaMonthly, $startingCash, $safetyFloor, $low);

        return new SalarySimulation(
            sustainableSalary: $low,
            startingCash: $startingCash,
            safetyFloor: $safetyFloor,
            trajectory: $final['trajectory'],
            minBalance: $final['minBalance'],
            bottleneckMonth: $final['bottleneckMonth'],
            yearEndAfterQ4: $final['yearEndAfterQ4'],
        );
    }

    /**
     * Simule la trajectoire à un salaire donné (sans recherche binaire).
     * Utile pour calculer le cushion réel au salaire actuel de l'utilisateur.
     */
    public function simulateAt(User $user, int $year, int $salary, int $startingCash = 0): SalarySimulation
    {
        $monthly = $this->monthlyAggregates($user, $year);
        $quarterly = $this->quarterlyTaxes($user, $year);
        $rentaMonthly = $this->rentaMonthlyAccrual($user, $year);

        $result = $this->simulate($monthly, $quarterly, $rentaMonthly, $startingCash, 0, $salary);

        return new SalarySimulation(
            sustainableSalary: $salary,
            startingCash: $startingCash,
            safetyFloor: 0,
            trajectory: $result['trajectory'],
            minBalance: $result['minBalance'],
            bottleneckMonth: $result['bottleneckMonth'],
            yearEndAfterQ4: $result['yearEndAfterQ4'],
        );
    }

    private function rentaMonthlyAccrual(User $user, int $year): int
    {
        $renta = $this->renta->forYear($user, $year);

        return (int) round($renta->restanteRenta / 12);
    }

    /**
     * @return array<int, array{income:int, expense:int}>
     */
    private function monthlyAggregates(User $user, int $year): array
    {
        $rows = $user->movements()
            ->whereYear('estimated_on', $year)
            ->whereIn('kind', [MovementKind::Income->value, MovementKind::Expense->value])
            ->get(['kind', 'amount', 'estimated_on']);

        $agg = [];
        for ($m = 1; $m <= 12; $m++) {
            $agg[$m] = ['income' => 0, 'expense' => 0];
        }

        foreach ($rows as $row) {
            $month = $row->estimated_on->month;
            $bucket = $row->kind === MovementKind::Income ? 'income' : 'expense';
            $agg[$month][$bucket] += (int) $row->amount;
        }

        return $agg;
    }

    /**
     * @return array<int, int> trimestre (1..4) → total à payer à Hacienda (IVA + Modelo 130) en centimes
     */
    private function quarterlyTaxes(User $user, int $year): array
    {
        $profile = $user->financialProfile ?? $user->financialProfile()->make();
        $quarters = [];

        for ($q = 1; $q <= 4; $q++) {
            $from = CarbonImmutable::create($year, ($q - 1) * 3 + 1, 1);
            $to = $from->addMonthsNoOverflow(2)->endOfMonth();

            $income = $user->movements()
                ->ofKind(MovementKind::Income)
                ->inWindow($from, $to)
                ->get();
            $expense = $user->movements()
                ->ofKind(MovementKind::Expense)
                ->inWindow($from, $to)
                ->get();

            $taxes = $this->provisioning->forPeriod($income, $expense, $profile);
            $quarters[$q] = $taxes->total();
        }

        return $quarters;
    }

    /**
     * @param  array<int, array{income:int, expense:int}>  $monthly
     */
    private function upperBound(array $monthly): int
    {
        $totalIncome = 0;
        foreach ($monthly as $row) {
            $totalIncome += $row['income'];
        }

        // Borne supérieure : revenu mensuel moyen. Le salaire ne peut
        // matériellement pas dépasser cette valeur sans tomber en déficit.
        return (int) ceil($totalIncome / 12);
    }

    /**
     * @param  array<int, array{income:int, expense:int}>  $monthly
     * @param  array<int, int>  $quarterly
     * @return array{isOk:bool, trajectory: array<int,int>, minBalance:int, bottleneckMonth:?int, yearEndAfterQ4:int}
     */
    private function simulate(array $monthly, array $quarterly, int $rentaMonthly, int $startingCash, int $safetyFloor, int $salary): array
    {
        $cash = $startingCash;
        $minBalance = PHP_INT_MAX;
        $bottleneckMonth = null;
        $trajectory = [];

        for ($m = 1; $m <= 12; $m++) {
            $cash += $monthly[$m]['income'] - $monthly[$m]['expense'] - $salary - $rentaMonthly;

            // Paiement effectif : Q1 → mois 4, Q2 → mois 7, Q3 → mois 10.
            // Q4 est payé le 30 janvier N+1 et reste donc hors fenêtre cash.
            $taxQ = match ($m) {
                4 => 1,
                7 => 2,
                10 => 3,
                default => null,
            };
            if ($taxQ !== null) {
                $cash -= $quarterly[$taxQ];
            }

            $trajectory[$m] = $cash;
            if ($cash < $minBalance) {
                $minBalance = $cash;
                $bottleneckMonth = $m;
            }
        }

        // Fin d'année : on doit pouvoir payer Q4 en janvier sans découvert.
        $endOfYearAfterQ4 = $cash - $quarterly[4];
        if ($endOfYearAfterQ4 < $minBalance) {
            $minBalance = $endOfYearAfterQ4;
            $bottleneckMonth = 12;
        }

        return [
            'isOk' => $minBalance >= $safetyFloor,
            'trajectory' => $trajectory,
            'minBalance' => $minBalance,
            'bottleneckMonth' => $bottleneckMonth,
            'yearEndAfterQ4' => $endOfYearAfterQ4,
        ];
    }
}
