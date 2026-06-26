<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $profile = $request->user()->financialProfile()->firstOrCreate([]);

        return Inertia::render('PerfilFiscal/Edit', [
            'profile' => [
                'full_name' => $profile->full_name,
                'nif' => $profile->nif,
                'activity' => $profile->activity,
                'province' => $profile->province,
                'regime' => $profile->regime?->value ?? 'direct_simplified',
                'iva_default' => (float) $profile->iva_default,
                'irpf_default' => (float) $profile->irpf_default,
                'cuota_monthly' => $profile->cuota_monthly / 100,
                'surcharge_equivalence' => (bool) $profile->surcharge_equivalence,
                'intra_community' => (bool) $profile->intra_community,
            ],
        ]);
    }

    public function update(FinancialProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->financialProfile()->firstOrCreate([]);
        $profile->update($request->attributesForModel());

        return back()->with('success', 'Perfil fiscal actualizado.');
    }
}
