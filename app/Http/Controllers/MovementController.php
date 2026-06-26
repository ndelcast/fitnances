<?php

namespace App\Http\Controllers;

use App\Http\Requests\MovementRequest;
use App\Models\Movement;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today();

        $movements = $user->movements()
            ->with('category:id,name,type')
            ->orderByDesc('estimated_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Movement $m) => [
                'id' => $m->id,
                'kind' => $m->kind->value,
                'label' => $m->label,
                'client_name' => $m->client_name,
                'amount' => $m->amount / 100,
                'iva_rate' => $m->iva_rate !== null ? (float) $m->iva_rate : null,
                'irpf_rate' => $m->irpf_rate !== null ? (float) $m->irpf_rate : null,
                'has_iva' => (bool) $m->has_iva,
                'has_irpf' => (bool) $m->has_irpf,
                'category_id' => $m->category_id,
                'category_name' => $m->category?->name,
                'estimated_on' => $m->estimated_on->toDateString(),
                'status' => $m->estimated_on->lte($today) ? 'realizado' : 'previsto',
                'is_paid' => $m->paid_at !== null,
                'is_recurring' => $m->cash_flow_row_id !== null,
            ]);

        $categories = $user->categories()
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type->value,
            ]);

        return Inertia::render('Movements/Index', [
            'movements' => $movements,
            'categories' => $categories,
        ]);
    }

    public function store(MovementRequest $request): RedirectResponse
    {
        $request->user()->movements()->create($request->attributesForModel());

        return back()->with('success', 'Movimiento creado.');
    }

    public function update(MovementRequest $request, Movement $movement): RedirectResponse
    {
        $this->authorizeOwner($request, $movement);

        $movement->update($request->attributesForModel());

        return back()->with('success', 'Movimiento actualizado.');
    }

    public function destroy(Request $request, Movement $movement): RedirectResponse
    {
        $this->authorizeOwner($request, $movement);

        $movement->delete();

        return back()->with('success', 'Movimiento eliminado.');
    }

    public function togglePaid(Request $request, Movement $movement): RedirectResponse
    {
        $this->authorizeOwner($request, $movement);

        $movement->update([
            'paid_at' => $movement->paid_at ? null : now(),
        ]);

        return back();
    }

    private function authorizeOwner(Request $request, Movement $movement): void
    {
        abort_unless($movement->user_id === $request->user()->id, 403);
    }
}
