<?php

namespace App\Http\Controllers;

use App\Enums\AmbulanceStatus;
use App\Enums\DispatchStatus;
use App\Filament\Pages\LiveMap;
use App\Models\Dispatch;
use App\Services\FleetSimulator;
use App\Services\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Seguimiento compartido: página pública (sin usuario ni contraseña)
 * para que el paciente, su familia o el hospital vean la unidad.
 * Solo muestra lo necesario: no aparecen nombre ni datos clínicos del paciente.
 */
class TrackingController extends Controller
{
    public function show(string $token): View
    {
        $dispatch = $this->find($token);

        return view('tracking.show', [
            'dispatch' => $dispatch,
            'state' => $this->payload($dispatch),
        ]);
    }

    /** Lo consulta la página cada pocos segundos */
    public function state(string $token): JsonResponse
    {
        $dispatch = $this->find($token);

        // En la demo, el simulador avanza mientras alguien está viendo
        if (config('app.demo') && Cache::add('fleet-simulator:lock', true, 2)) {
            (new FleetSimulator)->tick();
            $dispatch = $this->find($token);
        }

        return response()->json($this->payload($dispatch));
    }

    private function payload(Dispatch $dispatch): array
    {
        $unit = $dispatch->ambulance;
        $open = $dispatch->trackingIsOpen();

        $data = [
            'code' => $dispatch->code,
            'type' => $dispatch->type->getLabel(),
            'status' => $dispatch->status->value,
            'step' => $this->step($dispatch),
            'headline' => $this->headline($dispatch),
            'open' => $open,
            'eta' => $open ? $this->eta($dispatch) : null,
            'unit' => $unit && $open && $unit->latitude ? [
                'code' => $unit->code,
                'lat' => $unit->latitude,
                'lng' => $unit->longitude,
                'status' => $unit->status->getLabel(),
                'color' => LiveMap::STATUS_COLORS[$unit->status->value] ?? '#0d9488',
                'updated' => $unit->last_location_at?->diffForHumans(),
            ] : null,
            // El punto de atención suele ser la casa del paciente: solo mientras
            // el enlace está abierto, y sin la dirección escrita
            'pickup' => $open && $dispatch->pickup_latitude ? [
                'lat' => $dispatch->pickup_latitude,
                'lng' => $dispatch->pickup_longitude,
            ] : null,
            'hospital' => $open && $dispatch->hospital ? [
                'lat' => $dispatch->hospital->latitude,
                'lng' => $dispatch->hospital->longitude,
                'label' => $dispatch->hospital->name,
            ] : null,
        ];

        return $data;
    }

    private function find(string $token): Dispatch
    {
        return Dispatch::with(['ambulance', 'hospital'])->where('tracking_token', $token)->firstOrFail();
    }

    /** Paso actual: 0 recibido, 1 en camino, 2 en el sitio, 3 trasladando, 4 finalizado */
    private function step(Dispatch $dispatch): int
    {
        if (in_array($dispatch->status, [DispatchStatus::Completed, DispatchStatus::Cancelled], true)) {
            return 4;
        }

        return match ($dispatch->ambulance?->status) {
            AmbulanceStatus::EnRoute => 1,
            AmbulanceStatus::OnScene => 2,
            AmbulanceStatus::Transporting, AmbulanceStatus::AtHospital => 3,
            default => $dispatch->status === DispatchStatus::Pending ? 0 : 1,
        };
    }

    private function headline(Dispatch $dispatch): string
    {
        if ($dispatch->status === DispatchStatus::Cancelled) {
            return 'El servicio fue cancelado';
        }

        if ($dispatch->status === DispatchStatus::Completed) {
            return 'Servicio finalizado';
        }

        if ($dispatch->trackingExpired()) {
            return 'Este enlace de seguimiento venció';
        }

        if ($dispatch->status === DispatchStatus::Pending || ! $dispatch->ambulance) {
            return 'Estamos asignando una unidad';
        }

        $unit = $dispatch->ambulance;

        return match ($unit->status) {
            AmbulanceStatus::EnRoute => "La unidad {$unit->code} va en camino",
            AmbulanceStatus::OnScene => "La unidad {$unit->code} llegó al sitio",
            AmbulanceStatus::Transporting => 'En traslado hacia ' . ($dispatch->hospital?->short_name ?? 'el centro de salud'),
            AmbulanceStatus::AtHospital => 'La unidad llegó a ' . ($dispatch->hospital?->short_name ?? 'el centro de salud'),
            default => "Unidad {$unit->code} asignada",
        };
    }

    /** Minutos estimados hasta el próximo punto (paciente u hospital) */
    private function eta(Dispatch $dispatch): ?array
    {
        $unit = $dispatch->ambulance;

        if (! $unit?->latitude) {
            return null;
        }

        $target = match ($unit->status) {
            AmbulanceStatus::EnRoute => $dispatch->pickup_latitude ? [$dispatch->pickup_latitude, $dispatch->pickup_longitude, 'al sitio'] : null,
            AmbulanceStatus::Transporting => $dispatch->hospital ? [$dispatch->hospital->latitude, $dispatch->hospital->longitude, 'a ' . $dispatch->hospital->short_name] : null,
            default => null,
        };

        if (! $target) {
            return null;
        }

        $config = config('operations.tracking');
        $km = Geo::km($unit->latitude, $unit->longitude, $target[0], $target[1]) * $config['road_factor'];
        $minutes = max(1, (int) ceil($km / $config['average_speed_kmh'] * 60));

        return [
            'minutes' => $minutes,
            'km' => round($km, 1),
            'target' => $target[2],
            'arrival' => now()->addMinutes($minutes)->format('g:i a'),
        ];
    }
}
