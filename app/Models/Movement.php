<?php

namespace App\Models;

use App\Enums\MovementKind;
use App\Enums\MovementSource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unique modèle représentant un mouvement d'argent : facture (income),
 * charge (expense), salaire (salary) ou impôt (tax). Peut être ponctual
 * (cash_flow_row_id = null) ou récurrent (rattaché à une row qui groupe
 * ses occurrences mensuelles).
 *
 * Deux dates :
 * - estimated_on : date prévue / projection
 * - paid_at      : date réelle de paiement (null = encore pendiente)
 */
class Movement extends Model
{
    /** @use HasFactory<\Database\Factories\MovementFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cash_flow_plan_id',
        'cash_flow_row_id',
        'category_id',
        'kind',
        'label',
        'client_name',
        'amount',
        'estimated_on',
        'paid_at',
        'iva_rate',
        'irpf_rate',
        'has_iva',
        'has_irpf',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MovementKind::class,
            'source' => MovementSource::class,
            'amount' => 'integer',
            'iva_rate' => 'decimal:2',
            'irpf_rate' => 'decimal:2',
            'estimated_on' => 'date',
            'paid_at' => 'datetime',
            'has_iva' => 'boolean',
            'has_irpf' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cashFlowPlan(): BelongsTo
    {
        return $this->belongsTo(CashFlowPlan::class);
    }

    public function cashFlowRow(): BelongsTo
    {
        return $this->belongsTo(CashFlowRow::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereNotNull('paid_at');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('paid_at');
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('kind', MovementKind::Income);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('kind', MovementKind::Expense);
    }

    public function scopeOfKind(Builder $query, MovementKind ...$kinds): Builder
    {
        return $query->whereIn('kind', array_map(fn ($k) => $k->value, $kinds));
    }

    public function scopeInWindow(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween('estimated_on', [$from->toDateString(), $to->toDateString()]);
    }
}
