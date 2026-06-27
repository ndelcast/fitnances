<?php

namespace App\Http\Controllers;

use App\Exports\CashFlowExporter;
use App\Models\CashFlowRow;
use App\Models\Movement;
use App\Services\CashFlowPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowController extends Controller
{
    public function __construct(private readonly CashFlowPlanService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $year = (int) $request->query('year', CarbonImmutable::today()->year);

        $plan = $this->service->forYear($user, $year);
        $plan->load(['rows.movements']);
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
                'iva' => array_fill(0, 4, 0),  // computed live côté Vue
                'irpf' => array_fill(0, 4, 0),
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
            'incomes.*.id' => ['nullable', 'integer'],
            'incomes.*.label' => ['required', 'string', 'max:255'],
            'incomes.*.clientName' => ['nullable', 'string', 'max:255'],
            'incomes.*.categoryId' => ['nullable', 'integer'],
            'incomes.*.hasIva' => ['nullable', 'boolean'],
            'incomes.*.hasIrpf' => ['nullable', 'boolean'],
            'incomes.*.monthly' => ['required', 'array', 'size:12'],
            'incomes.*.monthly.*' => ['nullable', 'numeric'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.id' => ['nullable', 'integer'],
            'expenses.*.label' => ['required', 'string', 'max:255'],
            'expenses.*.categoryId' => ['nullable', 'integer'],
            'expenses.*.hasIva' => ['nullable', 'boolean'],
            'expenses.*.monthly' => ['required', 'array', 'size:12'],
            'expenses.*.monthly.*' => ['nullable', 'numeric'],
            'salary' => ['nullable', 'array'],
            'salary.id' => ['nullable', 'integer'],
            'salary.label' => ['nullable', 'string'],
            'salary.monthly' => ['nullable', 'array', 'size:12'],
            'salary.monthly.*' => ['nullable', 'numeric'],
        ]);

        $this->service->save($plan, $payload);

        return back()->with('success', 'Plan guardado.');
    }

    public function export(Request $request, int $year, CashFlowExporter $exporter): StreamedResponse
    {
        $plan = $request->user()->cashFlowPlans()->where('year', $year)->firstOrFail();

        return $exporter->downloadResponse($plan);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CashFlowRow>  $rows
     */
    private function mapRows($rows, bool $withClient = false): array
    {
        return $rows->sortBy('sort_order')->values()->map(function (CashFlowRow $row) use ($withClient) {
            $movementsByMonth = $row->movements->keyBy(fn (Movement $m) => $m->estimated_on->month);
            $monthly = [];
            foreach (range(1, 12) as $m) {
                $mv = $movementsByMonth->get($m);
                $monthly[] = $mv ? $mv->amount / 100 : 0;
            }

            $entry = [
                'id' => $row->id,
                'label' => $row->label,
                'categoryId' => $row->category_id,
                'hasIva' => (bool) $row->has_iva,
                'hasIrpf' => (bool) $row->has_irpf,
                'monthly' => $monthly,
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
            ];
        }

        $movementsByMonth = $row->movements->keyBy(fn (Movement $m) => $m->estimated_on->month);
        $monthly = [];
        foreach (range(1, 12) as $m) {
            $mv = $movementsByMonth->get($m);
            $monthly[] = $mv ? $mv->amount / 100 : 0;
        }

        return [
            'id' => $row->id,
            'label' => $row->label,
            'monthly' => $monthly,
        ];
    }
}
