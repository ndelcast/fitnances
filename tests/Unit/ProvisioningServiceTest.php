<?php

namespace Tests\Unit;

use App\Enums\MovementKind;
use App\Models\FinancialProfile;
use App\Models\Movement;
use App\Services\ProvisioningService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProvisioningServiceTest extends TestCase
{
    private function income(array $defs): Collection
    {
        return collect($defs)->map(fn (array $d) => new Movement(array_merge([
            'kind' => MovementKind::Income,
            'has_iva' => true,
            'has_irpf' => true,
            'iva_rate' => null,
            'irpf_rate' => null,
        ], $d)));
    }

    private function expense(array $defs): Collection
    {
        return collect($defs)->map(fn (array $d) => new Movement(array_merge([
            'kind' => MovementKind::Expense,
            'has_iva' => true,
            'has_irpf' => false,
            'iva_rate' => null,
            'irpf_rate' => null,
        ], $d)));
    }

    public function test_iva_extraite_du_ttc(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 0,
        ]);

        $provisions = (new ProvisioningService)->forPeriod(
            $this->income([['amount' => 121000, 'has_irpf' => false]]),
            collect(),
            $profile,
        );

        $this->assertSame(21000, $provisions->iva);
    }

    public function test_iva_deductible_reduit_la_provision(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 0,
        ]);

        $provisions = (new ProvisioningService)->forPeriod(
            $this->income([['amount' => 121000, 'has_irpf' => false]]),
            $this->expense([['amount' => 24200]]),
            $profile,
        );

        $this->assertSame(16800, $provisions->iva);
    }

    public function test_sin_iva_donne_zero(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 0,
        ]);

        $provisions = (new ProvisioningService)->forPeriod(
            $this->income([['amount' => 100000, 'has_iva' => false, 'has_irpf' => false]]),
            collect(),
            $profile,
        );

        $this->assertSame(0, $provisions->iva);
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
            $this->income([['amount' => 121000]]),
            collect(),
            $profile,
        );

        $this->assertSame(5000, $provisions->irpf);
    }

    public function test_sin_irpf_retenido_full_modelo_130(): void
    {
        $profile = new FinancialProfile([
            'iva_default' => 21,
            'irpf_default' => 15,
        ]);

        // Movement sans IRPF retenido → 20% du rendimiento sans déduction.
        $provisions = (new ProvisioningService)->forPeriod(
            $this->income([['amount' => 121000, 'has_irpf' => false]]),
            collect(),
            $profile,
        );

        $this->assertSame(20000, $provisions->irpf);
    }
}
