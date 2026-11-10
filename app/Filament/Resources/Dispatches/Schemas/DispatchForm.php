<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Patient;
use App\Services\DuplicateDetector;
use App\Services\UnitAvailability;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DispatchForm
{
    /**
     * Si el teléfono o la cédula coinciden con un paciente ya atendido, llena
     * solos el nombre, la dirección y la ubicación (sin borrar lo ya escrito).
     */
    private static function recognizePatient(?string $phone, ?string $nationalId, Get $get, Set $set): void
    {
        $patient = Patient::recognize($phone, $nationalId);
        $set('patient_id', $patient?->id);

        if (! $patient) {
            return;
        }

        foreach ([
            'patient_name' => $patient->full_name,
            'pickup_address' => $patient->address,
            'pickup_latitude' => $patient->latitude,
            'pickup_longitude' => $patient->longitude,
        ] as $field => $value) {
            if (blank($get($field)) && filled($value)) {
                $set($field, $value);
            }
        }
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Aviso de posible duplicado: otra llamada por la misma emergencia
                Callout::make('Posible solicitud duplicada')
                    ->description(fn (Get $get, ?Dispatch $record) => app(DuplicateDetector::class)->warning([
                        'caller_phone' => $get('caller_phone'),
                        'patient_id' => $get('patient_id'),
                        'pickup_latitude' => $get('pickup_latitude'),
                        'pickup_longitude' => $get('pickup_longitude'),
                    ], $record))
                    ->warning()
                    ->visible(fn (Get $get, ?Dispatch $record) => app(DuplicateDetector::class)->find([
                        'caller_phone' => $get('caller_phone'),
                        'patient_id' => $get('patient_id'),
                        'pickup_latitude' => $get('pickup_latitude'),
                        'pickup_longitude' => $get('pickup_longitude'),
                    ], $record)->isNotEmpty())
                    ->columnSpanFull(),

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
                        // Con el teléfono o la cédula se reconoce al paciente frecuente
                        TextInput::make('caller_phone')
                            ->label('Teléfono de quien llama')
                            ->tel()
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => self::recognizePatient($state, $get('patient_national_id'), $get, $set)),

                        TextInput::make('patient_national_id')
                            ->label('Cédula del paciente (opcional)')
                            ->helperText('Para reconocerlo la próxima vez. Se guarda cifrada.')
                            ->maxLength(30)
                            ->dehydrated(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => self::recognizePatient($get('caller_phone'), $state, $get, $set)),

                        Hidden::make('patient_id'),

                        Callout::make(fn (Get $get) => Patient::find($get('patient_id'))?->full_name)
                            ->description(fn (Get $get) => Patient::find($get('patient_id'))?->summary())
                            ->info()
                            ->visible(fn (Get $get) => filled($get('patient_id')))
                            ->columnSpanFull(),

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
                            ->live(onBlur: true)
                            ->label('Latitud')
                            ->numeric()
                            ->placeholder('10.4568'),

                        TextInput::make('pickup_longitude')
                            ->live(onBlur: true)
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
