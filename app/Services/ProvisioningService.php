<?php

namespace App\Services;

use App\DTOs\TaxProvisions;
use App\Models\FinancialProfile;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Calcule les provisions fiscales d'un autónomo : IVA (Modelo 303)
 * et IRPF (Modelo 130, pago fraccionado).
 *
 * Conventions :
 * - les montants des transactions sont TTC (telles qu'elles touchent le compte) ;
 * - chaque transaction porte son propre `iva_rate` et `irpf_rate` ; si null,
 *   on retombe sur les valeurs par défaut du profil fiscal.
 */
final class ProvisioningService
{
    private const IRPF_PAGO_FRACCIONADO_RATE = 0.20;

    /**
     * @param  Collection<int, Transaction>  $incomeTransactions
     * @param  Collection<int, Transaction>  $expenseTransactions
     */
    public function forPeriod(
        Collection $incomeTransactions,
        Collection $expenseTransactions,
        FinancialProfile $profile,
    ): TaxProvisions {
        $defaultIvaRate = (float) $profile->iva_default / 100;
        $defaultIrpfRate = (float) $profile->irpf_default / 100;

        $ivaCollected = 0;
        $irpfRetained = 0;
        $incomeHt = 0;

        foreach ($incomeTransactions as $transaction) {
            $ttc = (int) $transaction->amount;
            $ivaRate = $this->resolveRate($transaction->iva_rate, $defaultIvaRate);
            $irpfRate = $this->resolveRate($transaction->irpf_rate, $defaultIrpfRate);

            $ivaPart = $this->extractIva($ttc, $ivaRate);
            $ht = $ttc - $ivaPart;

            $ivaCollected += $ivaPart;
            $irpfRetained += (int) round($ht * $irpfRate);
            $incomeHt += $ht;
        }

        $ivaDeductible = 0;
        $expenseHt = 0;

        foreach ($expenseTransactions as $transaction) {
            $ttc = (int) $transaction->amount;
            $ivaRate = $this->resolveRate($transaction->iva_rate, $defaultIvaRate);

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
     * Retourne le taux à appliquer : celui de la transaction si renseigné
     * (y compris 0 = sin IVA/IRPF), sinon le défaut du profil.
     */
    private function resolveRate($transactionRate, float $defaultRate): float
    {
        return $transactionRate !== null
            ? ((float) $transactionRate) / 100
            : $defaultRate;
    }

    private function extractIva(int $ttc, float $rate): int
    {
        if ($rate <= 0) {
            return 0;
        }

        return (int) round($ttc * $rate / (1 + $rate));
    }
}
