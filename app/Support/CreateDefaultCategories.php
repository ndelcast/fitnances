<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\User;

/**
 * Crée le jeu de catégories par défaut pour un nouvel autónomo
 * (catégories ES classiques, modifiables ensuite).
 */
final class CreateDefaultCategories
{
    private const INCOME = [
        'Servicios profesionales',
        'Otros ingresos',
    ];

    private const EXPENSE = [
        'Alquiler oficina',
        'Cuota autónomos',
        'Software / suscripciones',
        'Material de oficina',
        'Suministros',
        'Telefonía e internet',
        'Honorarios profesionales',
        'Comidas y viajes',
        'Formación',
    ];

    public function for(User $user): void
    {
        if ($user->categories()->exists()) {
            return;
        }

        foreach (self::INCOME as $name) {
            $user->categories()->create([
                'name' => $name,
                'type' => TransactionType::Income,
            ]);
        }

        foreach (self::EXPENSE as $name) {
            $user->categories()->create([
                'name' => $name,
                'type' => TransactionType::Expense,
            ]);
        }
    }
}
