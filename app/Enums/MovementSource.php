<?php

namespace App\Enums;

enum MovementSource: string
{
    case Manual = 'manual';
    case Recurring = 'recurring';
    case Imported = 'imported';
}
