<?php

namespace Tests\Unit;

use App\Models\FinancialProfile;
use App\Models\Transaction;
use App\Services\ProvisioningService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProvisioningServiceTest extends TestCase
{
    private function transactions(int ...$amounts): Collection
    {
        return collect($amounts)->map(fn (int $amount) => new Transaction(['amount' => $amount]));
    }

    public function test_iva_sur_les_encaissements_sans_iva_deductible(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 0,
        ]);

        // 1210 € TTC → 210 € d'IVA (21 % sur 1000 € HT).
        $provisions = (new ProvisioningService)->forPeriod(
            $this->transactions(121000),
            collect(),
            $profile,
        );

        $this->assertSame(21000, $provisions->iva);
        // Modelo 130 = 20 % du rendimiento neto (1000 € HT) = 200 €.
        $this->assertSame(20000, $provisions->irpf);
    }

    public function test_iva_deductible_reduit_la_provision(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 0,
        ]);

        // Encaissé 1210 € TTC (210 € IVA repercutido).
        // Dépensé 242 € TTC (42 € IVA soportado).
        // IVA dû = 210 - 42 = 168 €.
        $provisions = (new ProvisioningService)->forPeriod(
            $this->transactions(121000),
            $this->transactions(24200),
            $profile,
        );

        $this->assertSame(16800, $provisions->iva);
    }

    public function test_irpf_pago_fraccionado_apres_retenciones(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 15,
        ]);

        // 1210 € TTC encaissés → 1000 € HT, 150 € retenus (15 % de 1000 €).
        // Modelo 130 base = 20 % de 1000 € = 200 €. Solde dû = 200 - 150 = 50 €.
        $provisions = (new ProvisioningService)->forPeriod(
            $this->transactions(121000),
            collect(),
            $profile,
        );

        $this->assertSame(5000, $provisions->irpf);
    }

    public function test_irpf_a_zero_si_retencion_couvre_tout(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 30, // taux fictif supérieur au pago fraccionado
        ]);

        // 1210 € TTC → 1000 € HT. Retenu 300 €, base Modelo 130 = 200 €. Solde = 0.
        $provisions = (new ProvisioningService)->forPeriod(
            $this->transactions(121000),
            collect(),
            $profile,
        );

        $this->assertSame(0, $provisions->irpf);
    }

    public function test_pas_d_iva_si_intra_communautaire_taux_zero(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 0,
            'irpf_default' => 0,
        ]);

        $provisions = (new ProvisioningService)->forPeriod(
            $this->transactions(100000),
            $this->transactions(20000),
            $profile,
        );

        $this->assertSame(0, $provisions->iva);
    }

    public function test_total_additionne_iva_et_irpf(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 15,
        ]);

        $provisions = (new ProvisioningService)->forPeriod(
            $this->transactions(121000),
            collect(),
            $profile,
        );

        $this->assertSame($provisions->iva + $provisions->irpf, $provisions->total());
    }
}
