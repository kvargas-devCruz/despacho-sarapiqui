<?php

namespace App\Filament\Resources\Hospitals\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HospitalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del centro')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        TextInput::make('short_name')
                            ->label('Nombre corto')
                            ->placeholder('Ej: H. Guápiles')
                            ->maxLength(50),

                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Ubicación en el mapa')
                    ->description('Coordenadas en formato decimal. Puedes obtenerlas en Google Maps con clic derecho sobre el punto.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('latitude')
                            ->label('Latitud')
                            ->numeric()
                            ->required(),

                        TextInput::make('longitude')
                            ->label('Longitud')
                            ->numeric()
                            ->required(),
                    ]),
            ]);
    }
}