<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today();

        $transactions = $user->transactions()
            ->with('category:id,name,type')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'type' => $t->type->value,
                'label' => $t->label,
                'amount' => $t->amount / 100,
                'iva_rate' => $t->iva_rate !== null ? (float) $t->iva_rate : null,
                'irpf_rate' => $t->irpf_rate !== null ? (float) $t->irpf_rate : null,
                'category_id' => $t->category_id,
                'category_name' => $t->category?->name,
                'occurred_on' => $t->occurred_on->toDateString(),
                'status' => $t->occurred_on->lte($today) ? 'realizado' : 'previsto',
            ]);

        $categories = $user->categories()
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type->value,
            ]);

        return Inertia::render('Transacciones/Index', [
            'transactions' => $transactions,
            'categories' => $categories,
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $request->user()->transactions()->create($request->attributesForModel());

        return back()->with('success', 'Transacción creada.');
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeOwner($request, $transaction);

        $transaction->update($request->attributesForModel());

        return back()->with('success', 'Transacción actualizada.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeOwner($request, $transaction);

        $transaction->delete();

        return back()->with('success', 'Transacción eliminada.');
    }

    public function realize(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeOwner($request, $transaction);

        $transaction->update(['occurred_on' => CarbonImmutable::today()]);

        return back()->with('success', 'Marcada como realizada.');
    }

    private function authorizeOwner(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
    }
}
