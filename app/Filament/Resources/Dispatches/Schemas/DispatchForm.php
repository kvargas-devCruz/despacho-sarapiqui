<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Filament\Forms\Components\LocationPicker;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Patient;
use App\Services\DuplicateDetector;
use App\Services\Triage;
use App\Services\UnitAvailability;
use Closure;
use Filament\Forms\Components\Checkbox;
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

    private static function isEmergency(Get $get): bool
    {
        $type = $get('type');

        return ($type instanceof DispatchType ? $type : DispatchType::tryFrom((string) $type)) !== DispatchType::Transfer;
    }

    /** @return array{priority: ?DispatchPriority, reasons: list<string>, warnings: list<string>} */
    private static function triageResult(Get $get): array
    {
        $age = $get('patient_age');

        return app(Triage::class)->evaluate($get('chief_complaint'), (array) ($get('triage') ?? []), filled($age) ? (int) $age : null);
    }

    /** Al responder el guion se propone la prioridad; el despacho la puede cambiar */
    private static function suggestPriority(Get $get, Set $set): void
    {
        if (! self::isEmergency($get)) {
            return;
        }

        if ($priority = self::triageResult($get)['priority']) {
            $set('priority', $priority);
        }
    }

    private static function isBelowSuggestion(Get $get): bool
    {
        return self::isEmergency($get) && Triage::isBelow($get('priority'), self::triageResult($get)['priority']);
    }

    /** @return list<ToggleButtons> */
    private static function triageQuestions(): array
    {
        return collect(config('operations.intake.questions'))
            ->map(fn (array $question, string $key) => ToggleButtons::make("triage.{$key}")
                ->label($question['label'])
                ->boolean('Sí', 'No')
                ->inline()
                ->grouped()
                ->visible(fn (Get $get) => self::isEmergency($get))
                ->required(fn (string $operation, Get $get) => $operation === 'create' && self::isEmergency($get))
                ->validationMessages(['required' => 'Haga esta pregunta del guion.'])
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::suggestPriority($get, $set)))
            ->values()
            ->all();
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

                Section::make('1. Tipo de llamada')
                    ->description('Guion: "Emergencias Zurquí, ¿cuál es su emergencia?". Si es una cita o un traslado entre hospitales, elija Traslado.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ToggleButtons::make('type')
                            ->label('Tipo de servicio')
                            ->options(DispatchType::class)
                            ->default(DispatchType::Emergency)
                            ->inline()
                            ->live()
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

                Section::make('2. Quién llama y paciente')
                    ->description('Guion: "¿A qué número lo puedo llamar si se corta?". Con el teléfono o la cédula se reconoce al paciente frecuente.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        // Con el teléfono o la cédula se reconoce al paciente frecuente
                        TextInput::make('caller_phone')
                            ->label('Teléfono de quien llama')
                            ->tel()
                            ->maxLength(20)
                            ->required(fn (string $operation, Get $get) => $operation === 'create' && self::isEmergency($get))
                            ->validationMessages(['required' => 'Pida un número para devolver la llamada si se corta.'])
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
                            ->required(fn (string $operation, Get $get) => $operation === 'create' && ! self::isEmergency($get))
                            ->validationMessages(['required' => 'Un traslado necesita el nombre del paciente.'])
                            ->maxLength(150),

                        TextInput::make('patient_age')
                            ->label('Edad')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(120)
                            ->suffix('años')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::suggestPriority($get, $set)),
                    ]),

                Section::make('3. Ubicación')
                    ->description('Guion: "¿Dónde está exactamente? ¿Qué hay cerca: escuela, iglesia, pulpería?". Marque el punto en el mapa y léale la referencia para confirmarla.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('pickup_address')
                            ->label('Dirección de recogida')
                            ->placeholder('Ej: La Virgen, frente a la escuela')
                            ->required()
                            ->columnSpanFull(),

                        LocationPicker::make('pickup_map')
                            ->label('Punto en el mapa')
                            ->columnSpanFull(),

                        TextInput::make('pickup_latitude')
                            ->live(onBlur: true)
                            ->label('Latitud')
                            ->numeric()
                            ->minValue(8)
                            ->maxValue(11.3)
                            ->placeholder('10.4568')
                            ->required(fn (string $operation, Get $get) => $operation === 'create' && self::isEmergency($get))
                            ->validationMessages(['required' => 'Marque el punto en el mapa.', 'min' => 'El punto está fuera de Costa Rica.', 'max' => 'El punto está fuera de Costa Rica.']),

                        TextInput::make('pickup_longitude')
                            ->live(onBlur: true)
                            ->label('Longitud')
                            ->numeric()
                            ->minValue(-86)
                            ->maxValue(-82.5)
                            ->placeholder('-84.0175')
                            ->required(fn (string $operation, Get $get) => $operation === 'create' && self::isEmergency($get))
                            ->validationMessages(['required' => 'Marque el punto en el mapa.', 'min' => 'El punto está fuera de Costa Rica.', 'max' => 'El punto está fuera de Costa Rica.']),

                        Checkbox::make('location_confirmed')
                            ->label('Confirmé el punto con quien llama')
                            ->accepted(fn (string $operation, Get $get) => $operation === 'create' && self::isEmergency($get))
                            ->validationMessages(['accepted' => 'Léale la referencia del punto a quien llama y confírmela.'])
                            ->columnSpanFull(),

                        Select::make('hospital_id')
                            ->label('Centro de salud de destino')
                            ->relationship('hospital', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (string $operation, Get $get) => $operation === 'create' && ! self::isEmergency($get))
                            ->validationMessages(['required' => 'Un traslado necesita el centro de salud de destino.'])
                            ->columnSpanFull(),
                    ]),

                Section::make('4. Motivo y prioridad')
                    ->description(fn (Get $get) => self::isEmergency($get)
                        ? 'Guion: haga las preguntas en este orden. El sistema sugiere la prioridad; usted decide.'
                        : 'Guion: "¿El paciente necesita oxígeno, camilla o silla de ruedas? ¿Viaja con acompañante?". Anótelo en la descripción.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('chief_complaint')
                            ->label('Motivo principal')
                            ->options(Triage::complaintOptions())
                            ->visible(fn (Get $get) => self::isEmergency($get))
                            ->required(fn (string $operation, Get $get) => $operation === 'create' && self::isEmergency($get))
                            ->validationMessages(['required' => 'Elija el motivo principal de la llamada.'])
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::suggestPriority($get, $set))
                            ->columnSpanFull(),

                        ...self::triageQuestions(),

                        Callout::make(fn (Get $get) => 'Prioridad sugerida: ' . (self::triageResult($get)['priority']?->getLabel() ?? '—'))
                            ->description(fn (Get $get) => 'Por: ' . implode(', ', self::triageResult($get)['reasons']) . '.')
                            ->color(fn (Get $get) => self::triageResult($get)['priority']?->getColor() ?? 'gray')
                            ->visible(fn (Get $get) => self::isEmergency($get) && self::triageResult($get)['priority'] !== null)
                            ->columnSpanFull(),

                        Callout::make('Atención')
                            ->description(fn (Get $get) => implode(' ', self::triageResult($get)['warnings']))
                            ->danger()
                            ->visible(fn (Get $get) => self::isEmergency($get) && self::triageResult($get)['warnings'] !== [])
                            ->columnSpanFull(),

                        ToggleButtons::make('priority')
                            ->label('Prioridad')
                            ->options(DispatchPriority::class)
                            ->default(DispatchPriority::Medium)
                            ->inline()
                            ->live()
                            ->required(),

                        TextInput::make('priority_note')
                            ->label('¿Por qué una prioridad más baja que la sugerida?')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => self::isBelowSuggestion($get))
                            ->required(fn (Get $get) => self::isBelowSuggestion($get))
                            ->validationMessages(['required' => 'Anote por qué baja la prioridad sugerida.']),

                        Textarea::make('description')
                            ->label('Motivo / descripción del caso')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('5. Asignación')
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
