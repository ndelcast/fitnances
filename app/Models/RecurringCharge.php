<?php

namespace App\Models;

use App\Enums\ChargeFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringCharge extends Model
{
    /** @use HasFactory<\Database\Factories\RecurringChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'label',
        'amount',
        'frequency',
        'day_of_month',
        'next_due_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => ChargeFrequency::class,
            'amount' => 'integer',
            'day_of_month' => 'integer',
            'next_due_on' => 'date',
            'is_active' => 'boolean',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
