<?php

namespace App\Filament\Pages;

use App\Enums\AmbulanceStatus;
use App\Enums\DispatchStatus;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Hospital;
use App\Services\FleetSimulator;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Renderless;
use UnitEnum;

class LiveMap extends Page
{
    protected string $view = 'filament.pages.live-map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Mapa en vivo';

    protected static ?string $title = 'Mapa en vivo';

    /** Color de cada estado en el mapa */
    public const STATUS_COLORS = [
        'available' => '#16a34a',
        'en_route' => '#f59e0b',
        'on_scene' => '#dc2626',
        'transporting' => '#2563eb',
        'at_hospital' => '#7c3aed',
        'out_of_service' => '#6b7280',
    ];

    /**
     * Datos que el mapa consulta cada pocos segundos.
     * #[Renderless] evita que Livewire vuelva a dibujar la página en cada consulta.
     */
    #[Renderless]
    public function getMapData(): array
    {
        // En modo demo, el simulador avanza mientras alguien tiene el mapa abierto.
        // El candado evita que varios visitantes lo ejecuten al mismo tiempo.
        if (config('app.demo') && Cache::add('fleet-simulator:lock', true, 2)) {
            (new FleetSimulator)->tick();
        }

        $units = Ambulance::query()
            ->with('activeDispatch.hospital')
            ->whereNotNull('latitude')
            ->orderBy('code')
            ->get()
            ->map(fn (Ambulance $unit) => [
                'id' => $unit->id,
                'code' => $unit->code,
                'lat' => $unit->latitude,
                'lng' => $unit->longitude,
                'status' => $unit->status->value,
                'statusLabel' => $unit->status->getLabel(),
                'color' => self::STATUS_COLORS[$unit->status->value],
                'type' => $unit->type->getLabel(),
                'speed' => $unit->speed ? round($unit->speed) : null,
                'updated' => $unit->last_location_at?->diffForHumans(),
                'liveGps' => $unit->hasLiveGps(),
                'dispatch' => $unit->activeDispatch ? [
                    'code' => $unit->activeDispatch->code,
                    'type' => $unit->activeDispatch->type->getLabel(),
                    'patient' => $unit->activeDispatch->patient_name,
                    'destination' => $unit->activeDispatch->hospital?->short_name,
                ] : null,
            ])
            ->values()
            ->all();

        $hospitals = Hospital::all()
            ->map(fn (Hospital $h) => [
                'name' => $h->name,
                'lat' => $h->latitude,
                'lng' => $h->longitude,
                'phone' => $h->phone,
            ])
            ->all();

        $pending = Dispatch::query()
            ->where('status', DispatchStatus::Pending)
            ->whereNotNull('pickup_latitude')
            ->get()
            ->map(fn (Dispatch $d) => [
                'code' => $d->code,
                'lat' => $d->pickup_latitude,
                'lng' => $d->pickup_longitude,
                'type' => $d->type->getLabel(),
                'priority' => $d->priority->getLabel(),
                'address' => $d->pickup_address,
                'description' => $d->description,
                'since' => $d->created_at->diffForHumans(),
            ])
            ->all();

        $statuses = collect(AmbulanceStatus::cases())
            ->map(fn (AmbulanceStatus $s) => [
                'value' => $s->value,
                'label' => $s->getLabel(),
                'color' => self::STATUS_COLORS[$s->value],
            ])
            ->all();

        return compact('units', 'hospitals', 'pending', 'statuses');
    }
}
