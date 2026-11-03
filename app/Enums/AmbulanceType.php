<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AmbulanceType: string implements HasLabel
{
    case Basic = 'basic';
    case Advanced = 'advanced';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Basic => 'Soporte Vital Básico',
            self::Advanced => 'Soporte Vital Avanzado',
            self::Transfer => 'Unidad de traslado',
        };
    }
}