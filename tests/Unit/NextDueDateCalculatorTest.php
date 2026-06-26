<?php

namespace Tests\Unit;

use App\Enums\ChargeFrequency;
use App\Support\NextDueDateCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class NextDueDateCalculatorTest extends TestCase
{
    public function test_mensuel_avant_le_jour_cible(): void
    {
        $from = CarbonImmutable::parse('2026-06-10');

        $next = (new NextDueDateCalculator)->compute(ChargeFrequency::Monthly, 20, $from);

        $this->assertSame('2026-06-20', $next->toDateString());
    }

    public function test_mensuel_apres_le_jour_cible(): void
    {
        $from = CarbonImmutable::parse('2026-06-25');

        $next = (new NextDueDateCalculator)->compute(ChargeFrequency::Monthly, 20, $from);

        $this->assertSame('2026-07-20', $next->toDateString());
    }

    public function test_mensuel_jour_31_clampe_en_fevrier(): void
    {
        $from = CarbonImmutable::parse('2026-02-01');

        $next = (new NextDueDateCalculator)->compute(ChargeFrequency::Monthly, 31, $from);

        $this->assertSame('2026-02-28', $next->toDateString());
    }

    public function test_annuel_avance_dun_an_si_le_jour_est_depasse(): void
    {
        $from = CarbonImmutable::parse('2026-06-25');

        $next = (new NextDueDateCalculator)->compute(ChargeFrequency::Yearly, 15, $from);

        $this->assertSame('2027-06-15', $next->toDateString());
    }

    public function test_trimestriel_avance_de_trois_mois_si_jour_depasse(): void
    {
        $from = CarbonImmutable::parse('2026-06-25');

        $next = (new NextDueDateCalculator)->compute(ChargeFrequency::Quarterly, 10, $from);

        $this->assertSame('2026-09-10', $next->toDateString());
    }
}
