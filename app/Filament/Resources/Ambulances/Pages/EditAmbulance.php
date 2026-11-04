<?php

namespace App\Filament\Resources\Ambulances\Pages;

use App\Filament\Resources\Ambulances\AmbulanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmbulance extends EditRecord
{
    protected static string $resource = AmbulanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
