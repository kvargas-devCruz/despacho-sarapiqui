<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DispatchType: string implements HasLabel, HasColor, HasIcon
{
    case Emergency = 'emergency';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Emergency => 'Emergencia',
            self::Transfer => 'Traslado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Emergency => 'danger',
            self::Transfer => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Emergency => 'heroicon-o-exclamation-triangle',
            self::Transfer => 'heroicon-o-arrows-right-left',
        };
    }
}