<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cash_flow_plan_id',
        'category_id',
        'type',
        'amount',
        'iva_rate',
        'irpf_rate',
        'label',
        'occurred_on',
        'paid_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'amount' => 'integer',
            'iva_rate' => 'decimal:2',
            'irpf_rate' => 'decimal:2',
            'occurred_on' => 'date',
            'paid_at' => 'datetime',
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

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Income);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Expense);
    }
}
