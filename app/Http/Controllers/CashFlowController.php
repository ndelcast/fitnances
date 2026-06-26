<?php

namespace App\Http\Controllers;

use App\Enums\QuarterlyTaxKind;
use App\Models\CashFlowPlan;
use App\Models\CashFlowRow;
use App\Services\CashFlowPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function __construct(private readonly CashFlowPlanService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $year = (int) $request->query('year', CarbonImmutable::today()->year);

        $plan = $this->service->forYear($user, $year);
        $plan->load(['rows.cells', 'quarterlyTaxes']);
        $profile = $user->financialProfile;

        $rowsByKind = $plan->rows->groupBy(fn (CashFlowRow $row) => $row->kind->value);

        $categories = $user->categories()
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type->value,
            ]);

        $years = $user->cashFlowPlans()
            ->orderBy('year')
            ->pluck('year')
            ->push($year)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return Inertia::render('FlujoCaja/Index', [
            'year' => $year,
            'startingBalance' => $plan->starting_balance / 100,
            'irpfExempt' => $plan->irpf_exempt,
            'incomes' => $this->mapRows($rowsByKind->get('income', collect()), withClient: true),
            'expenses' => $this->mapRows($rowsByKind->get('expense', collect())),
            'salary' => $this->mapSalary($rowsByKind->get('salary', collect())->first()),
            'quarterlyTaxes' => [
                'iva' => $this->mapTaxes($plan, QuarterlyTaxKind::Iva),
                'irpf' => $this->mapTaxes($plan, QuarterlyTaxKind::Irpf),
            ],
            'taxRates' => [
                'iva' => $profile ? (float) $profile->iva_default : 21,
                'irpf' => $profile ? (float) $profile->irpf_default : 15,
                'modelo130' => 20,
            ],
            'categories' => $categories,
            'years' => $years,
        ]);
    }

    public function update(Request $request, int $year): RedirectResponse
    {
        $user = $request->user();
        $plan = $user->cashFlowPlans()->where('year', $year)->firstOrFail();

        $payload = $request->validate([
            'startingBalance' => ['nullable', 'numeric'],
            'irpfExempt' => ['nullable', 'boolean'],
            'incomes' => ['nullable', 'array'],
            'incomes.*.label' => ['required', 'string', 'max:255'],
            'incomes.*.clientName' => ['nullable', 'string', 'max:255'],
            'incomes.*.categoryId' => ['nullable', 'integer'],
            'incomes.*.hasIva' => ['nullable', 'boolean'],
            'incomes.*.monthly' => ['required', 'array', 'size:12'],
            'incomes.*.monthly.*' => ['nullable', 'numeric'],
            'incomes.*.paid' => ['nullable', 'array'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.label' => ['required', 'string', 'max:255'],
            'expenses.*.categoryId' => ['nullable', 'integer'],
            'expenses.*.hasIva' => ['nullable', 'boolean'],
            'expenses.*.monthly' => ['required', 'array', 'size:12'],
            'expenses.*.monthly.*' => ['nullable', 'numeric'],
            'expenses.*.paid' => ['nullable', 'array'],
            'salary' => ['nullable', 'array'],
            'salary.label' => ['nullable', 'string'],
            'salary.monthly' => ['nullable', 'array', 'size:12'],
            'salary.monthly.*' => ['nullable', 'numeric'],
            'salary.paid' => ['nullable', 'array'],
            'quarterlyTaxes' => ['nullable', 'array'],
            'quarterlyTaxes.iva' => ['nullable', 'array', 'size:4'],
            'quarterlyTaxes.iva.*' => ['nullable', 'numeric'],
            'quarterlyTaxes.irpf' => ['nullable', 'array', 'size:4'],
            'quarterlyTaxes.irpf.*' => ['nullable', 'numeric'],
        ]);

        $this->service->save($plan, $payload);

        return back()->with('success', 'Plan guardado.');
    }

    private function mapRows($rows, bool $withClient = false): array
    {
        return $rows->sortBy('sort_order')->values()->map(function (CashFlowRow $row) use ($withClient) {
            $cellsByMonth = $row->cells->keyBy('month');
            $monthly = [];
            $paid = [];
            foreach (range(1, 12) as $m) {
                $cell = $cellsByMonth->get($m);
                $monthly[] = $cell ? $cell->amount / 100 : 0;
                $paid[] = $cell && $cell->paid_at !== null;
            }

            $entry = [
                'id' => $row->id,
                'label' => $row->label,
                'categoryId' => $row->category_id,
                'hasIva' => (bool) $row->has_iva,
                'monthly' => $monthly,
                'paid' => $paid,
            ];
            if ($withClient) {
                $entry['clientName'] = $row->client_name;
            }

            return $entry;
        })->all();
    }

    private function mapSalary(?CashFlowRow $row): array
    {
        if (! $row) {
            return [
                'id' => null,
                'label' => 'Salario',
                'monthly' => array_fill(0, 12, 0),
                'paid' => array_fill(0, 12, false),
            ];
        }

        $cellsByMonth = $row->cells->keyBy('month');
        $monthly = [];
        $paid = [];
        foreach (range(1, 12) as $m) {
            $cell = $cellsByMonth->get($m);
            $monthly[] = $cell ? $cell->amount / 100 : 0;
            $paid[] = $cell && $cell->paid_at !== null;
        }

        return [
            'id' => $row->id,
            'label' => $row->label,
            'monthly' => $monthly,
            'paid' => $paid,
        ];
    }

    private function mapTaxes(CashFlowPlan $plan, QuarterlyTaxKind $kind): array
    {
        $taxes = $plan->quarterlyTaxes
            ->where('kind', $kind)
            ->keyBy('quarter');

        return array_map(
            fn (int $q) => isset($taxes[$q]) ? $taxes[$q]->amount / 100 : 0,
            [1, 2, 3, 4],
        );
    }
}
