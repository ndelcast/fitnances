<?php

namespace App\Enums;

enum ExpectedIncomeStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Late = 'late';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À venir',
            self::Received => 'Encaissée',
            self::Late => 'En retard',
        };
    }
}
