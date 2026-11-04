<?php

namespace App\Filament\Resources\Ambulances\Schemas;

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AmbulanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la unidad')
                    ->description('Identificación y tipo de soporte que brinda la unidad.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->placeholder('SAR-07')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),

                        TextInput::make('plate')
                            ->label('Placa')
                            ->placeholder('AMB-1107')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),

                        Select::make('type')
                            ->label('Tipo de unidad')
                            ->options(AmbulanceType::class)
                            ->required(),

                        Select::make('status')
                            ->label('Estado actual')
                            ->options(AmbulanceStatus::class)
                            ->default(AmbulanceStatus::Available)
                            ->required(),
                    ]),

                Section::make('Tripulación y rastreo')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('user_id')
                            ->label('Paramédico a cargo')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin asignar'),

                        Toggle::make('is_simulated')
                            ->label('Unidad simulada')
                            ->helperText('Las unidades simuladas se mueven automáticamente en el mapa para la demostración.')
                            ->inline(false),
                    ]),
            ]);
    }
}