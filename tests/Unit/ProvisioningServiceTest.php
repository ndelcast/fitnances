<?php

namespace Tests\Unit;

use App\Models\FinancialProfile;
use App\Models\Transaction;
use App\Services\ProvisioningService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProvisioningServiceTest extends TestCase
{
    private function income(int ...$amounts): Collection
    {
        return collect($amounts)->map(fn (int $amount) => new Transaction(['amount' => $amount]));
    }

    public function test_urssaf_only_sans_tva(): void
    {
        $profile = new FinancialProfile([
            'urssaf_rate' => 22,
            'collects_vat' => false,
            'income_tax_rate' => 0,
        ]);

        // 3000 € encaissés, pas de TVA.
        $breakdown = (new ProvisioningService)->forIncome($this->income(300000), $profile);

        $this->assertSame(0, $breakdown->vat);
        $this->assertSame(66000, $breakdown->urssaf); // 22% de 3000 €
        $this->assertSame(0, $breakdown->incomeTax);
        $this->assertSame(66000, $breakdown->total());
    }

    public function test_extrait_la_tva_du_ttc_puis_urssaf_sur_le_ht(): void
    {
        $profile = new FinancialProfile([
            'urssaf_rate' => 22,
            'collects_vat' => true,
            'vat_rate' => 20,
            'income_tax_rate' => 0,
        ]);

        // 1200 € TTC : 200 € de TVA, 1000 € HT, 220 € d'URSSAF.
        $breakdown = (new ProvisioningService)->forIncome($this->income(120000), $profile);

        $this->assertSame(20000, $breakdown->vat);
        $this->assertSame(22000, $breakdown->urssaf);
        $this->assertSame(42000, $breakdown->total());
    }

    public function test_provision_impot_sur_le_ht(): void
    {
        $profile = new FinancialProfile([
            'urssaf_rate' => 22,
            'collects_vat' => false,
            'income_tax_rate' => 11,
        ]);

        // 1000 € HT : 220 € URSSAF + 110 € IR.
        $breakdown = (new ProvisioningService)->forIncome($this->income(100000), $profile);

        $this->assertSame(22000, $breakdown->urssaf);
        $this->assertSame(11000, $breakdown->incomeTax);
        $this->assertSame(33000, $breakdown->total());
    }

    public function test_additionne_plusieurs_encaissements(): void
    {
        $profile = new FinancialProfile([
            'urssaf_rate' => 22,
            'collects_vat' => false,
            'income_tax_rate' => 0,
        ]);

        $breakdown = (new ProvisioningService)->forIncome($this->income(100000, 200000), $profile);

        $this->assertSame(66000, $breakdown->urssaf); // 22% de 3000 €
    }
}
