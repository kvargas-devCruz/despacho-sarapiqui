<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AmbulanceStatus: string implements HasLabel, HasColor, HasIcon
{
    case Available = 'available';
    case EnRoute = 'en_route';
    case OnScene = 'on_scene';
    case Transporting = 'transporting';
    case AtHospital = 'at_hospital';
    case OutOfService = 'out_of_service';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::EnRoute => 'En camino',
            self::OnScene => 'En escena',
            self::Transporting => 'Trasladando paciente',
            self::AtHospital => 'En hospital',
            self::OutOfService => 'Fuera de servicio',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::EnRoute => 'warning',
            self::OnScene => 'danger',
            self::Transporting => 'info',
            self::AtHospital => 'primary',
            self::OutOfService => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::EnRoute => 'heroicon-o-arrow-trending-up',
            self::OnScene => 'heroicon-o-map-pin',
            self::Transporting => 'heroicon-o-truck',
            self::AtHospital => 'heroicon-o-building-office-2',
            self::OutOfService => 'heroicon-o-no-symbol',
        };
    }
}