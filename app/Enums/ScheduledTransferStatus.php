<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScheduledTransferStatus: string implements HasLabel, HasColor
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Dispatched = 'dispatched';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::Confirmed => 'Confirmado',
            self::Dispatched => 'En servicio',
            self::Cancelled => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Confirmed => 'info',
            self::Dispatched => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Ocupa unidad y cuenta para los choques de horario */
    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }
}
