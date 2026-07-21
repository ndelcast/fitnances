<?php

namespace App\Models;

use App\Enums\CashFlowRowKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashFlowRow extends Model
{
    protected $fillable = [
        'plan_id',
        'kind',
        'label',
        'client_name',
        'category_id',
        'has_iva',
        'has_irpf',
        'is_recurring',
        'recurrence_interval',
        'recurrence_start_month',
        'recurrence_end_month',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CashFlowRowKind::class,
            'has_iva' => 'boolean',
            'has_irpf' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_interval' => 'integer',
            'recurrence_start_month' => 'integer',
            'recurrence_end_month' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CashFlowPlan::class, 'plan_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class, 'cash_flow_row_id');
    }
}
