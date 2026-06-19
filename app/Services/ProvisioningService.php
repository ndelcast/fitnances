<?php

namespace App\Services;

use App\DTOs\ProvisionBreakdown;
use App\Models\FinancialProfile;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Calcule ce qu'un freelance doit mettre de côté (URSSAF / TVA / IR)
 * sur ses encaissements, selon son profil fiscal.
 *
 * Les montants des transactions sont TTC (tels qu'ils touchent le compte).
 */
final class ProvisioningService
{
    /**
     * @param  Collection<int, Transaction>  $incomeTransactions
     */
    public function forIncome(Collection $incomeTransactions, FinancialProfile $profile): ProvisionBreakdown
    {
        $urssafRate = (float) $profile->urssaf_rate / 100;
        $incomeTaxRate = (float) $profile->income_tax_rate / 100;
        $vatRate = $profile->collects_vat && $profile->vat_rate !== null
            ? (float) $profile->vat_rate / 100
            : 0.0;

        $vat = 0;
        $urssaf = 0;
        $incomeTax = 0;

        foreach ($incomeTransactions as $transaction) {
            $ttc = $transaction->amount;

            // Part de TVA collectée (à reverser), extraite du TTC.
            $vatPart = $vatRate > 0
                ? (int) round($ttc * $vatRate / (1 + $vatRate))
                : 0;

            // Le chiffre d'affaires HT sert de base à l'URSSAF et à l'IR.
            $ht = $ttc - $vatPart;

            $vat += $vatPart;
            $urssaf += (int) round($ht * $urssafRate);
            $incomeTax += (int) round($ht * $incomeTaxRate);
        }

        return new ProvisionBreakdown($vat, $urssaf, $incomeTax);
    }
}
