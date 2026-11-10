<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Resources\Patients\Pages\ManagePatients;
use App\Models\Patient;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pacientes ya atendidos. Se crean solos al registrar servicios; aquí se
 * corrigen datos y se anotan cuidados (oxígeno, silla de ruedas, acceso).
 */
class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'paciente';

    protected static ?string $pluralModelLabel = 'pacientes';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('full_name')
                    ->label('Nombre completo')
                    ->required()
                    ->maxLength(150),

                TextInput::make('national_id')
                    ->label('Cédula')
                    ->helperText('Se guarda cifrada.')
                    ->maxLength(30),

                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(20),

                TextInput::make('address')
                    ->label('Dirección habitual')
                    ->maxLength(255),

                Textarea::make('care_notes')
                    ->label('Cuidados que el despacho debe saber')
                    ->placeholder('Ej: usa oxígeno; silla de ruedas; casa con gradas; perro bravo')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),

                TextColumn::make('dispatches_count')
                    ->label('Servicios')
                    ->counts('dispatches')
                    ->badge()
                    ->sortable(),

                TextColumn::make('care_notes')
                    ->label('Cuidados')
                    ->limit(50)
                    ->placeholder('—'),

                TextColumn::make('updated_at')
                    ->label('Última vez')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePatients::route('/'),
        ];
    }
}
