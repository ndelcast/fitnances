<?php

namespace App\Services;

use App\DTOs\RentaProvision;
use App\Enums\MovementKind;
use App\Models\FinancialProfile;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Calcule la Renta annuelle (IRPF réel selon barème progressif)
 * et la provision mensuelle nécessaire pour la déclaration.
 *
 * Le Modelo 130 est une simple avance à 20 % du rendimiento. La Renta
 * applique le vrai barème ; à la fin de l'année on régularise.
 *
 * Approximations MVP :
 *  - Barème : état + autonomique général.
 *  - Seule la déduction "mínimo personal" est appliquée (5 550 €).
 *  - Pas de gastos de difícil justificación (7%), pas de réductions
 *    personnelles supplémentaires.
 */
final class RentaProvisionService
{
    // Barème IRPF 2025, état + autonomique général (approximation).
    private const BRACKETS = [
        ['upTo' => 12_450_00, 'rate' => 0.19],
        ['upTo' => 20_200_00, 'rate' => 0.24],
        ['upTo' => 35_200_00, 'rate' => 0.30],
        ['upTo' => 60_000_00, 'rate' => 0.37],
        ['upTo' => 300_000_00, 'rate' => 0.45],
        ['upTo' => PHP_INT_MAX, 'rate' => 0.47],
    ];

    private const MINIMUM_PERSONAL = 5_550_00; // 5 550 €

    private const MODELO_130_RATE = 0.20;

    public function forYear(User $user, int $year): RentaProvision
    {
        $profile = $user->financialProfile ?? $user->financialProfile()->make();
        $defaultIvaRate = (float) $profile->iva_default / 100;
        $defaultIrpfRate = (float) $profile->irpf_default / 100;

        $movements = $user->movements()
            ->whereYear('estimated_on', $year)
            ->whereIn('kind', [MovementKind::Income->value, MovementKind::Expense->value])
            ->get();

        [$incomeHt, $expenseHt, $incomeHtWithIrpf] = $this->splitHt($movements, $defaultIvaRate);

        $rendimientoNeto = max(0, $incomeHt - $expenseHt);
        $baseImponible = max(0, $rendimientoNeto - self::MINIMUM_PERSONAL);

        $rentaIrpf = $this->applyBrackets($baseImponible);
        $marginalRate = $this->marginalRateFor($baseImponible);

        $modelo130Annual = (int) round($rendimientoNeto * self::MODELO_130_RATE);
        $retentionsAnnual = (int) round($incomeHtWithIrpf * $defaultIrpfRate);

        $restanteRenta = max(0, $rentaIrpf - $modelo130Annual - $retentionsAnnual);
        $monthlyProvision = (int) round($restanteRenta / 12);

        return new RentaProvision(
            rendimientoNetoAnnual: $rendimientoNeto,
            baseImponible: $baseImponible,
            rentaIrpf: $rentaIrpf,
            marginalRate: $marginalRate,
            modelo130Annual: $modelo130Annual,
            retentionsAnnual: $retentionsAnnual,
            restanteRenta: $restanteRenta,
            monthlyProvision: $monthlyProvision,
        );
    }

    /**
     * @param  Collection<int, Movement>  $movements
     * @return array{0:int,1:int,2:int} [incomeHt, expenseHt, incomeHtWithIrpf]
     */
    private function splitHt(Collection $movements, float $defaultIvaRate): array
    {
        $incomeHt = 0;
        $expenseHt = 0;
        $incomeHtWithIrpf = 0;

        foreach ($movements as $m) {
            $rate = $this->ivaRateFor($m, $defaultIvaRate);
            $ht = $m->amount - $this->extractIva($m->amount, $rate);

            if ($m->kind === MovementKind::Income) {
                $incomeHt += $ht;
                if ($m->has_irpf) {
                    $incomeHtWithIrpf += $ht;
                }
            } else {
                $expenseHt += $ht;
            }
        }

        return [$incomeHt, $expenseHt, $incomeHtWithIrpf];
    }

    private function ivaRateFor(Movement $m, float $default): float
    {
        if (! $m->has_iva) {
            return 0;
        }

        return $m->iva_rate !== null ? ((float) $m->iva_rate) / 100 : $default;
    }

    private function extractIva(int $ttc, float $rate): int
    {
        return $rate <= 0 ? 0 : (int) round($ttc * $rate / (1 + $rate));
    }

    private function applyBrackets(int $base): int
    {
        if ($base <= 0) {
            return 0;
        }
        $owed = 0;
        $prev = 0;
        foreach (self::BRACKETS as $b) {
            $slice = min($base, $b['upTo']) - $prev;
            if ($slice <= 0) {
                break;
            }
            $owed += $slice * $b['rate'];
            $prev = $b['upTo'];
            if ($base <= $b['upTo']) {
                break;
            }
        }

        return (int) round($owed);
    }

    private function marginalRateFor(int $base): float
    {
        if ($base <= 0) {
            return self::BRACKETS[0]['rate'];
        }
        $rate = self::BRACKETS[0]['rate'];
        $prev = 0;
        foreach (self::BRACKETS as $b) {
            if ($base > $prev) {
                $rate = $b['rate'];
            }
            $prev = $b['upTo'];
            if ($base <= $b['upTo']) {
                break;
            }
        }

        return $rate;
    }
}
