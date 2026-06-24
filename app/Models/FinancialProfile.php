<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialProfile extends Model
{
    /** @use HasFactory<\Database\Factories\FinancialProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'urssaf_rate',
        'collects_vat',
        'vat_rate',
        'income_tax_rate',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'urssaf_rate' => 'decimal:2',
            'collects_vat' => 'boolean',
            'vat_rate' => 'decimal:2',
            'income_tax_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
