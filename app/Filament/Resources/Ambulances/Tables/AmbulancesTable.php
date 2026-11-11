<?php

namespace App\Filament\Resources\Ambulances\Tables;

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Models\Ambulance;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

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

                // App de tripulación: código QR para abrirla en el celular de la unidad
                Action::make('crew')
                    ->label('Tripulación')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('info')
                    ->visible(fn (Ambulance $record) => Gate::allows('update', $record))
                    ->modalHeading(fn (Ambulance $record) => "App de tripulación · {$record->code}")
                    ->modalWidth('lg')
                    ->modalContent(fn (Ambulance $record) => view('filament.ambulances.crew-link', ['unit' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->extraModalFooterActions(fn (Ambulance $record) => [
                        Action::make('rotateCrewLink')
                            ->label('Nuevo enlace')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalDescription('El enlace actual deja de servir en el celular que lo tenga.')
                            ->action(function () use ($record) {
                                $record->rotateCrewToken();
                                Notification::make()->title('Enlace nuevo creado')->body('Escanee el código otra vez en el celular de la tripulación.')->success()->send();
                            }),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
