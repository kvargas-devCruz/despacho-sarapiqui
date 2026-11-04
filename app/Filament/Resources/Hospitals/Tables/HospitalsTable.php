<?php

namespace App\Filament\Resources\Hospitals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HospitalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Centro de salud')
                    ->weight(FontWeight::Bold)
                    ->description(fn ($record) => $record->address)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('short_name')
                    ->label('Nombre corto')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—'),

                TextColumn::make('dispatches_count')
                    ->label('Servicios recibidos')
                    ->counts('dispatches')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}