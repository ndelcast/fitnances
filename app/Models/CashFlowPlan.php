<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashFlowPlan extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'starting_balance',
        'irpf_exempt',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'starting_balance' => 'integer',
            'irpf_exempt' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CashFlowRow::class, 'plan_id');
    }

    public function quarterlyTaxes(): HasMany
    {
        return $this->hasMany(QuarterlyTax::class, 'plan_id');
    }
}
