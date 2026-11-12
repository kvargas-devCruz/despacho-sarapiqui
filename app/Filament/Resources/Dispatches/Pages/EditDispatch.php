<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\Actions\ShareTrackingAction;
use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\Dispatches\Pages\Concerns\ReportsAssignmentErrors;
use App\Models\DispatchDocument;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditDispatch extends EditRecord
{
    use ReportsAssignmentErrors;

    protected static string $resource = DispatchResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->withAssignmentErrors(fn () => parent::handleRecordUpdate($record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [
            ShareTrackingAction::make(),
            Action::make('sheet')
                ->label('Hoja de traslado')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => Gate::allows('viewAny', DispatchDocument::class))
                ->url(fn () => route('transfer-documents.sheet', $this->getRecord()), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
