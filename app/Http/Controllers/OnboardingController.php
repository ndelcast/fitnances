<?php

namespace App\Http\Controllers;

use App\Enums\ChargeFrequency;
use App\Http\Requests\OnboardingRequest;
use App\Models\RecurringCharge;
use App\Support\CreateDefaultCategories;
use App\Support\NextDueDateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly NextDueDateCalculator $nextDue,
        private readonly CreateDefaultCategories $defaultCategories,
    ) {}

    public function show(Request $request): Response
    {
        $profile = $request->user()->financialProfile;

        return Inertia::render('Asistente/Index', [
            'initial' => [
                'annualRevenue' => null,
                'cuotaMonthly' => $profile?->cuota_monthly ? $profile->cuota_monthly / 100 : null,
                'monthlySalary' => $profile?->monthly_salary ? $profile->monthly_salary / 100 : null,
            ],
        ]);
    }

    public function store(OnboardingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $profile = $user->financialProfile()->firstOrCreate([]);
            $profile->update([
                'cuota_monthly' => (int) round(((float) $data['cuotaMonthly']) * 100),
                'monthly_salary' => (int) round(((float) $data['monthlySalary']) * 100),
            ]);

            $this->defaultCategories->for($user);

            $cuotaCategory = $user->categories()
                ->where('name', 'Cuota autónomos')
                ->where('type', 'expense')
                ->first();

            $existingCuota = $user->recurringCharges()
                ->where('label', 'Cuota autónomos')
                ->first();

            $cuotaAmount = (int) round(((float) $data['cuotaMonthly']) * 100);
            $nextDue = $this->nextDue->compute(ChargeFrequency::Monthly, 30, CarbonImmutable::today());

            if ($existingCuota) {
                $existingCuota->update([
                    'amount' => $cuotaAmount,
                    'next_due_on' => $nextDue,
                    'is_active' => true,
                ]);
            } else {
                RecurringCharge::create([
                    'user_id' => $user->id,
                    'category_id' => $cuotaCategory?->id,
                    'label' => 'Cuota autónomos',
                    'amount' => $cuotaAmount,
                    'frequency' => ChargeFrequency::Monthly,
                    'day_of_month' => 30,
                    'next_due_on' => $nextDue,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('flujo-caja.index')
            ->with('success', 'Tu asistente anual está listo.');
    }
}
