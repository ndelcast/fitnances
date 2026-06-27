<?php

namespace Tests\Unit;

use App\Enums\MovementKind;
use App\Models\FinancialProfile;
use App\Models\Movement;
use App\Models\User;
use App\Services\ProvisioningService;
use App\Services\RentaProvisionService;
use App\Services\SustainableSalaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SustainableSalaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SustainableSalaryService
    {
        return new SustainableSalaryService(new ProvisioningService, new RentaProvisionService);
    }

    private function userWithProfile(): User
    {
        $user = User::factory()->create();
        FinancialProfile::factory()->create([
            'user_id' => $user->id,
            'iva_default' => 21,
            'irpf_default' => 15,
        ]);

        return $user->refresh();
    }

    private function recurringMovement(User $user, int $year, MovementKind $kind, int $amount, bool $hasIva = false, bool $hasIrpf = false): void
    {
        for ($m = 1; $m <= 12; $m++) {
            Movement::factory()->create([
                'user_id' => $user->id,
                'kind' => $kind,
                'amount' => $amount,
                'estimated_on' => CarbonImmutable::create($year, $m, 15),
                'paid_at' => null,
                'has_iva' => $hasIva,
                'has_irpf' => $hasIrpf,
                'iva_rate' => null,
                'irpf_rate' => null,
            ]);
        }
    }

    public function test_sans_ingresos_le_salaire_soutenable_vaut_zero(): void
    {
        $user = $this->userWithProfile();

        $sim = $this->service()->forYear($user, 2026);

        $this->assertSame(0, $sim->sustainableSalary);
    }

    public function test_cas_simple_sans_iva_le_salaire_soutenable_couvre_le_modelo_130(): void
    {
        $user = $this->userWithProfile();
        // 3 000 €/mois ingreso (sans IVA, sans IRPF retención), 0 € de gastos.
        // Modelo 130 : 20 % × 36 000 = 7 200 €/an, imputés sur les 4 trimestres
        // (Q4 provisionné en décembre). Contrainte serrée en décembre :
        //   12 × (3000 − S) − 7 200 ≥ 0  →  S ≤ 2 400 €/mois (modulo Renta delta).
        $this->recurringMovement($user, 2026, MovementKind::Income, 300000);

        $sim = $this->service()->forYear($user, 2026);

        $this->assertGreaterThanOrEqual(238000, $sim->sustainableSalary);
        $this->assertLessThanOrEqual(240000, $sim->sustainableSalary);
    }

    public function test_les_taxes_trimestrielles_font_baisser_le_salaire_soutenable(): void
    {
        $user = $this->userWithProfile();
        // 3 000 € TTC/mes Acme (IVA 21 %), pas de IRPF retenido.
        // Charges : 1 180 €/mes (Alquiler 650 + Adobe 60 + Cuota 469).
        // IVA Q ≈ 1 190 €, IRPF 130 Q ≈ 854 € → ~2 040 €/trim à Hacienda.
        // Sustainable doit être nettement < (3 000 - 1 180) = 1 820 €.
        $this->recurringMovement($user, 2026, MovementKind::Income, 300000, hasIva: true);
        $this->recurringMovement($user, 2026, MovementKind::Expense, 65000, hasIva: true);   // Alquiler TTC
        $this->recurringMovement($user, 2026, MovementKind::Expense, 6049, hasIva: true);    // Adobe TTC
        $this->recurringMovement($user, 2026, MovementKind::Expense, 46900);                 // Cuota sans IVA

        $sim = $this->service()->forYear($user, 2026);

        // Sans taxes : 1 820 €. Avec taxes : nettement moins.
        $this->assertLessThan(150000, $sim->sustainableSalary, 'sustainable doit être < 1 500 €/mes avec taxes');
        $this->assertGreaterThan(100000, $sim->sustainableSalary, 'sustainable doit rester > 1 000 €/mes');
    }

    public function test_le_bottleneck_tombe_apres_la_premiere_echeance_hacienda(): void
    {
        $user = $this->userWithProfile();
        // Configuration acide : un ingreso unique en décembre, charges réparties.
        // → caisse plonge sur les premiers mois, bottleneck en mars ou avril.
        $this->recurringMovement($user, 2026, MovementKind::Expense, 100000, hasIva: true);
        Movement::factory()->create([
            'user_id' => $user->id,
            'kind' => MovementKind::Income,
            'amount' => 12000000, // 120 k€ TTC en décembre
            'estimated_on' => CarbonImmutable::create(2026, 12, 15),
            'paid_at' => null,
            'has_iva' => true,
            'has_irpf' => false,
            'iva_rate' => null,
            'irpf_rate' => null,
        ]);

        $sim = $this->service()->forYear($user, 2026, startingCash: 500000);

        $this->assertNotNull($sim->bottleneckMonth);
        $this->assertLessThanOrEqual(11, $sim->bottleneckMonth);
    }
}
