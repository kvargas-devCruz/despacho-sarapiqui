<?php

namespace App\Filament\Resources\Ambulances\Tables;

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AmbulancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Unidad')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plate')
                    ->label('Placa')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                TextColumn::make('activeDispatch.code')
                    ->label('Servicio activo')
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label('Paramédico')
                    ->placeholder('Sin asignar')
                    ->toggleable(),

                TextColumn::make('last_location_at')
                    ->label('Última señal GPS')
                    ->since()
                    ->sortable(),

                IconColumn::make('is_simulated')
                    ->label('Simulada')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->poll('10s')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(AmbulanceStatus::class),

                SelectFilter::make('type')
                    ->label('Tipo de unidad')
                    ->options(AmbulanceType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
