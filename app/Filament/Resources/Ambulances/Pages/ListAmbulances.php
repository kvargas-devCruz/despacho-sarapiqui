<?php

namespace App\Filament\Resources\Ambulances\Pages;

use App\Filament\Resources\Ambulances\AmbulanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmbulances extends ListRecords
{
    protected static string $resource = AmbulanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
