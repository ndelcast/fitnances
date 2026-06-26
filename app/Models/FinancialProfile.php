<?php

namespace App\Models;

use App\Enums\FiscalRegime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialProfile extends Model
{
    /** @use HasFactory<\Database\Factories\FinancialProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'nif',
        'activity',
        'province',
        'regime',
        'iva_default',
        'irpf_default',
        'cuota_monthly',
        'monthly_salary',
        'surcharge_equivalence',
        'intra_community',
        'onboarded_at',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'regime' => FiscalRegime::class,
            'iva_default' => 'decimal:2',
            'irpf_default' => 'decimal:2',
            'cuota_monthly' => 'integer',
            'monthly_salary' => 'integer',
            'surcharge_equivalence' => 'boolean',
            'intra_community' => 'boolean',
            'onboarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
