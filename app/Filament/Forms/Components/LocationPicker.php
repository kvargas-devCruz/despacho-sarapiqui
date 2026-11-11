<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Mapa para confirmar el punto de recogida: el despacho toca el mapa o
 * arrastra el pin, y se llenan solos los campos de latitud y longitud.
 * También busca la dirección escrita (OpenStreetMap, solo Costa Rica).
 */
class LocationPicker extends Field
{
    protected string $view = 'filament.forms.components.location-picker';

    protected string $latitudeField = 'pickup_latitude';

    protected string $longitudeField = 'pickup_longitude';

    protected string $addressField = 'pickup_address';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
    }

    public function fields(string $latitude, string $longitude, string $address): static
    {
        $this->latitudeField = $latitude;
        $this->longitudeField = $longitude;
        $this->addressField = $address;

        return $this;
    }

    public function getLatitudeStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->latitudeField);
    }

    public function getLongitudeStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->longitudeField);
    }

    public function getAddressStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->addressField);
    }

    /** @return array{0: float, 1: float} Centro inicial: Sarapiquí */
    public function getDefaultCenter(): array
    {
        return [10.40, -84.03];
    }
}
