<?php

namespace App\Filament\Resources\Hospitals\Pages;

use App\Filament\Resources\Hospitals\HospitalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Ficha en modo lectura: la usan los roles que pueden ver pero no editar */
class ViewHospital extends ViewRecord
{
    protected static string $resource = HospitalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
