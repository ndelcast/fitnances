<?php

namespace App\Enums;

enum MovementKind: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Salary = 'salary';
    case Tax = 'tax';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Ingreso',
            self::Expense => 'Gasto',
            self::Salary => 'Salario',
            self::Tax => 'Impuesto',
        };
    }
}
