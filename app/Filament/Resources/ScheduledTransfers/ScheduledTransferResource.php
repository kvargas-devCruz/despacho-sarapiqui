<?php

namespace App\Filament\Resources\ScheduledTransfers;

use App\Enums\AmbulanceType;
use App\Enums\ScheduledTransferStatus;
use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\ScheduledTransfers\Pages\ManageScheduledTransfers;
use App\Models\Ambulance;
use App\Models\ScheduledTransfer;
use App\Services\TransferSchedule;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Agenda de traslados programados y citas médicas.
 */
class ScheduledTransferResource extends Resource
{
    protected static ?string $model = ScheduledTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Agenda de traslados';

    protected static ?string $modelLabel = 'traslado programado';

    protected static ?string $pluralModelLabel = 'agenda de traslados';

    /** En el menú: traslados de hoy que siguen pendientes */
    public static function getNavigationBadge(): ?string
    {
        $count = ScheduledTransfer::whereDate('pickup_at', today())
            ->whereIn('status', [ScheduledTransferStatus::Scheduled, ScheduledTransferStatus::Confirmed])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Traslados de hoy por salir';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Paciente')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('patient_name')->label('Nombre')->required()->maxLength(150),
                        TextInput::make('patient_national_id')->label('Cédula')->maxLength(30),
                        TextInput::make('patient_phone')->label('Teléfono')->tel()->maxLength(30),
                        TextInput::make('reason')->label('Motivo')->placeholder('Ej: Hemodiálisis, control en oncología…')->maxLength(150)->columnSpanFull(),
                    ]),

                Section::make('Recogida y destino')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('pickup_address')->label('Dirección de recogida')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('pickup_latitude')->label('Latitud')->numeric()->helperText('Opcional: mejora el cálculo de la hora de salida.'),
                        TextInput::make('pickup_longitude')->label('Longitud')->numeric(),
                        Select::make('hospital_id')->label('Centro de salud')->relationship('hospital', 'name')->required()->preload(),
                        DateTimePicker::make('appointment_at')->label('Hora de la cita')->seconds(false)->minutesStep(5)->required()->live(onBlur: true),
                        Toggle::make('round_trip')->label('Con regreso a la casa')->helperText('La unidad espera la cita y lleva al paciente de vuelta.')->inline(false),
                        DateTimePicker::make('pickup_at')
                            ->label('Hora de recogida')
                            ->seconds(false)
                            ->minutesStep(5)
                            ->helperText('Déjela vacía y el sistema la calcula según la distancia.'),
                    ]),

                Section::make('Unidad')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('ambulance_type')->label('Tipo de unidad')->options(AmbulanceType::class)->default(AmbulanceType::Transfer)->required()->live(),
                        Select::make('ambulance_id')
                            ->label('Unidad asignada')
                            ->options(fn (Get $get) => Ambulance::query()
                                ->when($get('ambulance_type'), fn ($q, $type) => $q->where('type', $type instanceof AmbulanceType ? $type->value : $type))
                                ->orderBy('code')->pluck('code', 'id'))
                            ->placeholder('Asignar después'),
                        Select::make('status')->label('Estado')->options(ScheduledTransferStatus::class)->default(ScheduledTransferStatus::Scheduled)->required()
                            ->visibleOn('edit'),
                        Textarea::make('notes')->label('Indicaciones')->placeholder('Ej: paciente en silla de ruedas, requiere oxígeno')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('Se repite')
                    ->description('Para tratamientos frecuentes, como hemodiálisis o terapias.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visibleOn('create')
                    ->schema([
                        Toggle::make('repeat')->label('Repetir cada semana')->live()->inline(false),
                        DatePicker::make('repeat_until')->label('Hasta')->default(now()->addMonth())->visible(fn (Get $get) => $get('repeat')),
                        CheckboxList::make('repeat_days')
                            ->label('Días')
                            ->options([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'])
                            ->columns(7)
                            ->visible(fn (Get $get) => $get('repeat'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['hospital', 'ambulance']))
            ->defaultGroup(
                Group::make('pickup_at')
                    ->label('Día')
                    ->getKeyFromRecordUsing(fn (ScheduledTransfer $record) => $record->pickup_at->toDateString())
                    ->getTitleFromRecordUsing(fn (ScheduledTransfer $record) => ucfirst($record->pickup_at->locale('es')->translatedFormat('l j \d\e F')))
                    ->collapsible()
            )
            ->columns([
                TextColumn::make('pickup_at')
                    ->label('Recoger')
                    ->dateTime('g:i a')
                    ->description(fn (ScheduledTransfer $record) => 'Cita ' . $record->appointment_at->format('g:i a'))
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->description(fn (ScheduledTransfer $record) => $record->reason)
                    ->searchable(),

                TextColumn::make('pickup_address')
                    ->label('Desde')
                    ->limit(28)
                    ->tooltip(fn (ScheduledTransfer $record) => $record->pickup_address),

                TextColumn::make('hospital.short_name')
                    ->label('Hacia')
                    ->description(fn (ScheduledTransfer $record) => $record->round_trip ? 'Ida y regreso' : 'Solo ida'),

                TextColumn::make('ambulance.code')
                    ->label('Unidad')
                    ->badge()
                    ->color('gray')
                    ->placeholder(fn (ScheduledTransfer $record) => $record->ambulance_type->getLabel()),

                TextColumn::make('conflicts')
                    ->label('Avisos')
                    ->state(fn (ScheduledTransfer $record) => count(app(TransferSchedule::class)->conflicts($record)) ?: null)
                    ->formatStateUsing(fn ($state) => $state ? "⚠ {$state}" : null)
                    ->badge()
                    ->color('danger')
                    ->tooltip(fn (ScheduledTransfer $record) => implode("\n", app(TransferSchedule::class)->conflicts($record)) ?: null)
                    ->placeholder('✓'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->defaultSort('pickup_at')
            ->filters([
                Filter::make('upcoming')
                    ->label('Desde hoy')
                    ->query(fn (Builder $query) => $query->where('pickup_at', '>=', today()))
                    ->toggle()
                    ->default(),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ScheduledTransferStatus::class),

                SelectFilter::make('hospital_id')
                    ->label('Centro de salud')
                    ->relationship('hospital', 'short_name'),
            ])
            ->recordActions([
                Action::make('dispatch')
                    ->label('Enviar unidad')
                    ->authorize('update')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (ScheduledTransfer $record) => 'Se crea el servicio en el despacho' . ($record->ambulance ? " con la unidad {$record->ambulance->code}." : ' y se asigna la unidad libre más cercana.'))
                    ->visible(fn (ScheduledTransfer $record) => $record->status->isActive())
                    ->action(function (ScheduledTransfer $record, Action $action) {
                        try {
                            $dispatch = app(TransferSchedule::class)->dispatch($record);
                        } catch (ValidationException $exception) {
                            Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();
                            $action->halt();

                            return;
                        }

                        // La unidad agendada estaba ocupada: el servicio queda pendiente
                        if ($record->ambulance_id && ! $dispatch->ambulance_id) {
                            Notification::make()
                                ->title("Servicio {$dispatch->code} creado sin unidad")
                                ->body("La unidad {$record->ambulance?->code} está atendiendo otro servicio. Asigne otra desde Servicios.")
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("Servicio {$dispatch->code} creado")
                            ->body('Ya aparece en Servicios y en el mapa en vivo.')
                            ->success()
                            ->send();
                    }),

                Action::make('confirm')
                    ->label('Confirmar')
                    ->authorize('update')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('info')
                    ->tooltip('El paciente confirmó la cita')
                    ->visible(fn (ScheduledTransfer $record) => $record->status === ScheduledTransferStatus::Scheduled)
                    ->action(fn (ScheduledTransfer $record) => $record->update(['status' => ScheduledTransferStatus::Confirmed])),

                Action::make('service')
                    ->label('Ver servicio')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->visible(fn (ScheduledTransfer $record) => $record->dispatch_id !== null)
                    ->url(fn (ScheduledTransfer $record) => DispatchResource::getUrl('edit', ['record' => $record->dispatch_id])),

                EditAction::make()
                    ->visible(fn (ScheduledTransfer $record) => $record->status->isActive())
                    ->using(fn (ScheduledTransfer $record, array $data, EditAction $action) => static::saveOrWarn($action, fn () => tap($record)->update($data))),

                Action::make('cancel')
                    ->label('Cancelar')
                    ->authorize('update')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ScheduledTransfer $record) => $record->status->isActive())
                    ->action(fn (ScheduledTransfer $record) => $record->update(['status' => ScheduledTransferStatus::Cancelled])),
            ]);
    }

    /**
     * Guarda y, si la unidad ya está ocupada a esa hora, avisa sin cerrar el
     * formulario para que se pueda elegir otra unidad u otra hora.
     */
    public static function saveOrWarn(Action $action, Closure $save): mixed
    {
        try {
            return $save();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('No se puede agendar con esa unidad')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->persistent()
                ->send();

            $action->halt();
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageScheduledTransfers::route('/'),
        ];
    }
}
