<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Roles del despacho. Lo que puede hacer cada uno está en config/permissions.php.
 */
enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Dispatcher = 'dispatcher';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor de turno',
            self::Dispatcher => 'Despachador',
            self::Viewer => 'Solo consulta',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Supervisor => 'warning',
            self::Dispatcher => 'primary',
            self::Viewer => 'gray',
        };
    }
}
