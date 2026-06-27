<?php

namespace App\Services;

use App\DTOs\SalesHealth;
use App\Enums\MovementKind;
use App\Enums\MovementSource;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Calcule les indicateurs de santé commerciale d'un autónomo :
 *
 *  - Recurrencia  : % chiffre annuel d'origine récurrente
 *  - DSO          : Days Sales Outstanding = délai moyen
 *                   entre l'émission de la facture (issued_on) et
 *                   l'encaissement réel (paid_at).
 *  - Pendientes   : factures émises et non encore encaissées.
 */
final class SalesHealthService
{
    public function forYear(User $user, int $year): SalesHealth
    {
        $incomes = $user->movements()
            ->whereYear('estimated_on', $year)
            ->where('kind', MovementKind::Income->value)
            ->get();

        $totalAnnual = (int) $incomes->sum('amount');
        $recurrenciaAmount = (int) $incomes
            ->filter(fn ($m) => $m->source === MovementSource::Recurring || $m->cash_flow_row_id !== null)
            ->sum('amount');
        $recurrenciaPct = $totalAnnual > 0
            ? (int) round($recurrenciaAmount / $totalAnnual * 100)
            : 0;

        // DSO : seulement les income avec issued_on ET paid_at renseignés.
        $dsoSample = $incomes->filter(fn ($m) => $m->issued_on !== null && $m->paid_at !== null);
        $dsoSampleSize = $dsoSample->count();
        $avgDsoDays = $dsoSampleSize > 0
            ? (int) round($dsoSample->sum(fn ($m) => $m->issued_on->diffInDays($m->paid_at, false)) / $dsoSampleSize)
            : null;

        // Pendientes : émises (issued_on renseigné et passé) mais pas encore payées.
        $today = CarbonImmutable::today();
        $pendientes = $incomes->filter(
            fn ($m) => $m->paid_at === null
                && $m->issued_on !== null
                && $m->issued_on->lessThanOrEqualTo($today),
        );

        return new SalesHealth(
            recurrenciaPct: $recurrenciaPct,
            recurrenciaAmount: $recurrenciaAmount,
            totalAnnualIncome: $totalAnnual,
            recurrenciaScore: $recurrenciaPct,
            avgDsoDays: $avgDsoDays,
            dsoSampleSize: $dsoSampleSize,
            pendientesCount: $pendientes->count(),
            pendientesAmount: (int) $pendientes->sum('amount'),
        );
    }
}
