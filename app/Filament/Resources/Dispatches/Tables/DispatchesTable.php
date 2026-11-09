<?php

namespace App\Filament\Resources\Dispatches\Tables;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Filament\Resources\Dispatches\Actions\ShareTrackingAction;
use App\Models\Dispatch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DispatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->copyable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->searchable()
                    ->limit(25)
                    ->placeholder('—'),

                TextColumn::make('pickup_address')
                    ->label('Ubicación')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->pickup_address)
                    ->searchable(),

                TextColumn::make('ambulance.code')
                    ->label('Unidad')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sin asignar'),

                TextColumn::make('hospital.short_name')
                    ->label('Destino')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('cost_total')
                    ->label('Costo')
                    ->money('CRC', locale: 'es_CR', decimalPlaces: 0)
                    ->description(fn (Dispatch $record) => $record->distance_km ? number_format($record->distance_km, 1, ',', '.') . ' km' . ($record->distance_estimated ? ' (estimado)' : '') : null)
                    ->tooltip(fn (Dispatch $record) => $record->cost_total === null ? null : implode("\n", [
                        'Combustible: ₡' . number_format($record->cost_fuel, 0, ',', '.'),
                        'Tripulación: ₡' . number_format($record->cost_crew, 0, ',', '.') . " ({$record->duration_minutes} min)",
                        'Mantenimiento: ₡' . number_format($record->cost_maintenance, 0, ',', '.'),
                        'Insumos: ₡' . number_format($record->cost_supplies, 0, ',', '.'),
                        'Margen estimado: ₡' . number_format($record->margin(), 0, ',', '.'),
                    ]))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Recibido')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(DispatchType::class),

                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(DispatchPriority::class),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(DispatchStatus::class),
            ])
            ->recordActions([
                ShareTrackingAction::make(),
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
