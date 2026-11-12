<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Models\DispatchDocument;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

/** Ficha en modo lectura: la usan los roles que pueden ver pero no editar */
class ViewDispatch extends ViewRecord
{
    protected static string $resource = DispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('sheet')
                ->label('Hoja de traslado')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => Gate::allows('viewAny', DispatchDocument::class))
                ->url(fn () => route('transfer-documents.sheet', $this->getRecord()), shouldOpenInNewTab: true),
        ];
    }
}
