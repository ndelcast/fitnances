<?php

namespace App\Http\Controllers;

use App\Enums\CashFlowRowKind;
use App\Enums\MovementKind;
use App\Enums\MovementSource;
use App\Http\Requests\OnboardingRequest;
use App\Models\CashFlowRow;
use App\Models\Movement;
use App\Support\CreateDefaultCategories;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
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
                'onboarded_at' => now(),
            ]);

            $this->defaultCategories->for($user);

            $cuotaAmount = (int) round(((float) $data['cuotaMonthly']) * 100);
            $year = CarbonImmutable::today()->year;
            $plan = $user->cashFlowPlans()->firstOrCreate(
                ['year' => $year],
                ['starting_balance' => 0, 'irpf_exempt' => false],
            );

            // Crée (ou met à jour) la row Cuota autónomos avec 12 movements.
            $cuotaCategory = $user->categories()
                ->where('name', 'Cuota autónomos')
                ->where('type', MovementKind::Expense->value)
                ->first();

            $row = $plan->rows()
                ->where('kind', CashFlowRowKind::Expense->value)
                ->where('label', 'Cuota autónomos')
                ->first();

            if ($row) {
                $row->update(['category_id' => $cuotaCategory?->id]);
                $row->movements()->delete();
            } else {
                $row = CashFlowRow::create([
                    'plan_id' => $plan->id,
                    'kind' => CashFlowRowKind::Expense,
                    'label' => 'Cuota autónomos',
                    'category_id' => $cuotaCategory?->id,
                    'has_iva' => false,
                    'has_irpf' => false,
                    'sort_order' => 0,
                ]);
            }

            foreach (range(1, 12) as $month) {
                Movement::create([
                    'user_id' => $user->id,
                    'cash_flow_plan_id' => $plan->id,
                    'cash_flow_row_id' => $row->id,
                    'category_id' => $cuotaCategory?->id,
                    'kind' => MovementKind::Expense,
                    'label' => 'Cuota autónomos',
                    'amount' => $cuotaAmount,
                    'estimated_on' => CarbonImmutable::create($year, $month, 30)->toDateString(),
                    'paid_at' => null,
                    'has_iva' => false,
                    'has_irpf' => false,
                    'source' => MovementSource::Recurring,
                ]);
            }
        });

        return redirect()->route('cash-flow.index')
            ->with('success', 'Tu asistente anual está listo.');
    }
}
