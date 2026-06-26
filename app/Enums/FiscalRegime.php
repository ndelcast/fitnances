<?php

namespace App\Enums;

enum FiscalRegime: string
{
    case DirectSimplified = 'direct_simplified';
    case DirectNormal = 'direct_normal';
    case Modules = 'modules';

    public function label(): string
    {
        return match ($this) {
            self::DirectSimplified => 'Estimación directa simplificada',
            self::DirectNormal => 'Estimación directa normal',
            self::Modules => 'Estimación objetiva (módulos)',
        };
    }
}
