<?php

namespace App\Models;

use App\Enums\QuarterlyTaxKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterlyTax extends Model
{
    protected $fillable = [
        'plan_id',
        'kind',
        'quarter',
        'amount',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => QuarterlyTaxKind::class,
            'quarter' => 'integer',
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CashFlowPlan::class, 'plan_id');
    }
}
