<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowCell extends Model
{
    protected $fillable = [
        'row_id',
        'month',
        'amount',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(CashFlowRow::class, 'row_id');
    }
}
