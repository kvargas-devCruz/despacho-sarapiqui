<?php

namespace App\Filament\Resources\Ambulances\Pages;

use App\Filament\Resources\Ambulances\AmbulanceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Ficha en modo lectura: la usan los roles que pueden ver pero no editar */
class ViewAmbulance extends ViewRecord
{
    protected static string $resource = AmbulanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
