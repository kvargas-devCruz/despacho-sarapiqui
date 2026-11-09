<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Ficha en modo lectura: la usan los roles que pueden ver pero no editar */
class ViewDispatch extends ViewRecord
{
    protected static string $resource = DispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
