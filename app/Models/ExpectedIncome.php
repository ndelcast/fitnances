<?php

namespace App\Models;

use App\Enums\ExpectedIncomeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpectedIncome extends Model
{
    /** @use HasFactory<\Database\Factories\ExpectedIncomeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'client_name',
        'amount',
        'expected_on',
        'status',
        'received_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpectedIncomeStatus::class,
            'amount' => 'integer',
            'expected_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receivedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'received_transaction_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ExpectedIncomeStatus::Pending);
    }
}
