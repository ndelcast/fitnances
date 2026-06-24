<?php

namespace App\Enums;

enum TransactionSource: string
{
    case Manual = 'manual';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Saisie manuelle',
            self::Csv => 'Import CSV',
        };
    }
}
