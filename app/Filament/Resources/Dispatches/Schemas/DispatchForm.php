<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Services\UnitAvailability;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clasificación del servicio')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ToggleButtons::make('type')
                            ->label('Tipo de servicio')
                            ->options(DispatchType::class)
                            ->default(DispatchType::Emergency)
                            ->inline()
                            ->required(),

                        ToggleButtons::make('priority')
                            ->label('Prioridad')
                            ->options(DispatchPriority::class)
                            ->default(DispatchPriority::Medium)
                            ->inline()
                            ->required(),

                        Select::make('status')
                            ->label('Estado')
                            ->options(DispatchStatus::class)
                            ->default(DispatchStatus::Pending)
                            ->required(),

                        TextInput::make('code')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ]),

                Section::make('Paciente')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('patient_name')
                            ->label('Nombre o descripción')
                            ->placeholder('Ej: Masculino, identidad pendiente')
                            ->maxLength(150),

                        TextInput::make('patient_age')
                            ->label('Edad')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(120)
                            ->suffix('años'),

                        Textarea::make('description')
                            ->label('Motivo / descripción del caso')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Ubicación y destino')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('pickup_address')
                            ->label('Dirección de recogida')
                            ->placeholder('Ej: La Virgen, frente a la escuela')
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('pickup_latitude')
                            ->label('Latitud')
                            ->numeric()
                            ->placeholder('10.4568'),

                        TextInput::make('pickup_longitude')
                            ->label('Longitud')
                            ->numeric()
                            ->placeholder('-84.0175'),

                        Select::make('hospital_id')
                            ->label('Centro de salud de destino')
                            ->relationship('hospital', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),

                Section::make('Asignación')
                    ->columnSpanFull()
                    ->schema([
                        // Solo se ofrecen unidades libres (y la que ya tiene el servicio)
                        Select::make('ambulance_id')
                            ->label('Unidad asignada')
                            ->relationship(
                                'ambulance',
                                'code',
                                fn (Builder $query, ?Dispatch $record) => $query->whereIn(
                                    'ambulances.id',
                                    app(UnitAvailability::class)->assignableUnits($record)->select('id')
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Ambulance $record) => "{$record->code} · {$record->type->getLabel()}")
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin asignar')
                            ->helperText('Solo aparecen las unidades libres. Al asignar una, el servicio pasa a "Asignado".')
                            ->rules([
                                fn (?Dispatch $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $unit = $value ? Ambulance::find($value) : null;

                                    if ($unit && $reason = app(UnitAvailability::class)->unavailableReason($unit, $record)) {
                                        $fail($reason);
                                    }
                                },
                            ]),
                    ]),
            ]);
    }
}
