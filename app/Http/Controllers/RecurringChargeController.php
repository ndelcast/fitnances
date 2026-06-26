<?php

namespace App\Http\Controllers;

use App\Enums\ChargeFrequency;
use App\Http\Requests\RecurringChargeRequest;
use App\Models\RecurringCharge;
use App\Support\NextDueDateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringChargeController extends Controller
{
    public function __construct(private readonly NextDueDateCalculator $nextDue) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $charges = $user->recurringCharges()
            ->with('category:id,name,type')
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->get()
            ->map(fn (RecurringCharge $c) => [
                'id' => $c->id,
                'label' => $c->label,
                'amount' => $c->amount / 100,
                'frequency' => $c->frequency->value,
                'day_of_month' => $c->day_of_month,
                'category_id' => $c->category_id,
                'category_name' => $c->category?->name,
                'next_due_on' => $c->next_due_on->toDateString(),
                'is_active' => $c->is_active,
            ]);

        $categories = $user->categories()
            ->where('type', 'expense')
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type->value,
            ]);

        return Inertia::render('CargosRecurrentes/Index', [
            'charges' => $charges,
            'categories' => $categories,
        ]);
    }

    public function store(RecurringChargeRequest $request): RedirectResponse
    {
        $data = $request->attributesForModel();
        $data['next_due_on'] = $this->nextDue->compute(
            ChargeFrequency::from($data['frequency']),
            $data['day_of_month'],
            CarbonImmutable::today(),
        );

        $request->user()->recurringCharges()->create($data);

        return back()->with('success', 'Cargo creado.');
    }

    public function update(RecurringChargeRequest $request, RecurringCharge $cargo): RedirectResponse
    {
        $this->authorizeOwner($request, $cargo);

        $data = $request->attributesForModel();
        $data['next_due_on'] = $this->nextDue->compute(
            ChargeFrequency::from($data['frequency']),
            $data['day_of_month'],
            CarbonImmutable::today(),
        );

        $cargo->update($data);

        return back()->with('success', 'Cargo actualizado.');
    }

    public function destroy(Request $request, RecurringCharge $cargo): RedirectResponse
    {
        $this->authorizeOwner($request, $cargo);

        $cargo->delete();

        return back()->with('success', 'Cargo eliminado.');
    }

    public function toggle(Request $request, RecurringCharge $cargo): RedirectResponse
    {
        $this->authorizeOwner($request, $cargo);

        $cargo->update(['is_active' => ! $cargo->is_active]);

        return back();
    }

    private function authorizeOwner(Request $request, RecurringCharge $cargo): void
    {
        abort_unless($cargo->user_id === $request->user()->id, 403);
    }
}
