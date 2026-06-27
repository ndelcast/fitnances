<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Analyse financière produite par l'IA (Claude). On garde l'input
 * (chiffres envoyés) et le payload (réponse JSON structurée) pour
 * pouvoir réafficher la dernière analyse sans relancer un appel.
 */
class AiAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'input',
        'payload',
        'model',
        'input_tokens',
        'output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
