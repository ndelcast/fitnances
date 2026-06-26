<?php

namespace App\Services;

use App\DTOs\TaxProvisions;
use App\Models\FinancialProfile;
use App\Models\Movement;
use Illuminate\Support\Collection;

/**
 * Calcule les provisions fiscales d'un autónomo : IVA (Modelo 303)
 * et IRPF (Modelo 130, pago fraccionado).
 *
 * Conventions :
 * - les montants des movements sont TTC (telles qu'elles touchent le compte) ;
 * - chaque movement porte son propre `iva_rate` et `irpf_rate` ; si null,
 *   on retombe sur les valeurs par défaut du profil fiscal.
 */
final class ProvisioningService
{
    private const IRPF_PAGO_FRACCIONADO_RATE = 0.20;

    /**
     * @param  Collection<int, Movement>  $incomeMovements
     * @param  Collection<int, Movement>  $expenseMovements
     */
    public function forPeriod(
        Collection $incomeMovements,
        Collection $expenseMovements,
        FinancialProfile $profile,
    ): TaxProvisions {
        $defaultIvaRate = (float) $profile->iva_default / 100;
        $defaultIrpfRate = (float) $profile->irpf_default / 100;

        $ivaCollected = 0;
        $irpfRetained = 0;
        $incomeHt = 0;

        foreach ($incomeMovements as $movement) {
            $ttc = (int) $movement->amount;
            $ivaRate = $this->ivaRateFor($movement, $defaultIvaRate);
            $irpfRate = $this->irpfRateFor($movement, $defaultIrpfRate);

            $ivaPart = $this->extractIva($ttc, $ivaRate);
            $ht = $ttc - $ivaPart;

            $ivaCollected += $ivaPart;
            $irpfRetained += (int) round($ht * $irpfRate);
            $incomeHt += $ht;
        }

        $ivaDeductible = 0;
        $expenseHt = 0;

        foreach ($expenseMovements as $movement) {
            $ttc = (int) $movement->amount;
            $ivaRate = $this->ivaRateFor($movement, $defaultIvaRate);

            $ivaPart = $this->extractIva($ttc, $ivaRate);

            $ivaDeductible += $ivaPart;
            $expenseHt += $ttc - $ivaPart;
        }

        $ivaOwed = max(0, $ivaCollected - $ivaDeductible);

        // IRPF dû en Modelo 130 = 20 % du rendimiento neto - retenciones déjà appliquées.
        $netRendimiento = max(0, $incomeHt - $expenseHt);
        $irpfBase = (int) round($netRendimiento * self::IRPF_PAGO_FRACCIONADO_RATE);
        $irpfOwed = max(0, $irpfBase - $irpfRetained);

        return new TaxProvisions($ivaOwed, $irpfOwed);
    }

    /**
     * Priorité : (1) flag has_iva=false → 0,
     * (2) iva_rate non null → ce taux,
     * (3) défaut profil.
     */
    private function ivaRateFor(Movement $movement, float $default): float
    {
        if (! $movement->has_iva) {
            return 0;
        }

        return $movement->iva_rate !== null ? ((float) $movement->iva_rate) / 100 : $default;
    }

    private function irpfRateFor(Movement $movement, float $default): float
    {
        if (! $movement->has_irpf) {
            return 0;
        }

        return $movement->irpf_rate !== null ? ((float) $movement->irpf_rate) / 100 : $default;
    }

    private function extractIva(int $ttc, float $rate): int
    {
        if ($rate <= 0) {
            return 0;
        }

        return (int) round($ttc * $rate / (1 + $rate));
    }
}
