<?php

namespace App\Filament\Resources\Dispatches\RelationManagers;

use App\Models\DispatchEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Bitácora del servicio: cada cambio de estado con su hora, quién lo hizo y
 * cuántos minutos pasaron desde que entró la solicitud. Solo lectura.
 */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Bitácora';

    protected static ?string $modelLabel = 'evento';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Hora')
                    ->dateTime('d/m/Y g:i:s a'),

                TextColumn::make('elapsed')
                    ->label('Desde la solicitud')
                    ->state(fn (DispatchEvent $record) => round($this->getOwnerRecord()->created_at->diffInSeconds($record->created_at) / 60, 1) . ' min'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),

                TextColumn::make('note')
                    ->label('Detalle')
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
            ]);
    }
}
