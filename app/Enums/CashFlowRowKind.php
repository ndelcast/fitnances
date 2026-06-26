<?php

namespace App\Enums;

enum CashFlowRowKind: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Salary = 'salary';
}
