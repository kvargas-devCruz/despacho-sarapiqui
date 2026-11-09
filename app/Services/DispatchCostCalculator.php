<?php

namespace App\Services;

use App\Enums\DispatchType;
use App\Models\AmbulanceLocation;
use App\Models\Dispatch;

/**
 * Costo de operación de un servicio: combustible, tripulación,
 * mantenimiento e insumos, más una tarifa de referencia para ver el margen.
 *
 * La distancia sale del recorrido real que registró la unidad (GPS).
 * Si no hay recorrido, se estima con la ubicación del paciente y el hospital.
 */
class DispatchCostCalculator
{
    public function calculate(Dispatch $dispatch): array
    {
        $config = config('operations.costs');
        [$km, $estimated] = $this->distance($dispatch);
        $km = round($km, 1);

        $start = $dispatch->assigned_at ?? $dispatch->created_at;
        $end = $dispatch->completed_at ?? now();
        $minutes = $start ? max(1, (int) round($start->diffInMinutes($end, true))) : 0;

        $type = $dispatch->type instanceof DispatchType ? $dispatch->type->value : (string) $dispatch->type;

        $fuel = round($km / max($config['km_per_liter'], 0.1) * $config['fuel_price_per_liter']);
        $crew = round($minutes / 60 * $config['crew_hourly_cost']);
        $maintenance = round($km * $config['maintenance_per_km']);
        $supplies = (float) ($config['supplies'][$type] ?? 0);

        $tariff = config("operations.tariffs.{$type}", ['base' => 0, 'per_km' => 0]);

        return [
            'distance_km' => $km,
            'distance_estimated' => $estimated,
            'duration_minutes' => $minutes,
            'cost_fuel' => $fuel,
            'cost_crew' => $crew,
            'cost_maintenance' => $maintenance,
            'cost_supplies' => $supplies,
            'cost_total' => $fuel + $crew + $maintenance + $supplies,
            'reference_charge' => round($tariff['base'] + $km * $tariff['per_km']),
        ];
    }

    /** Guarda el costo en el servicio */
    public function apply(Dispatch $dispatch): Dispatch
    {
        $dispatch->forceFill($this->calculate($dispatch))->saveQuietly();

        return $dispatch;
    }

    /**
     * @return array{0: float, 1: bool} kilómetros y si fueron estimados
     */
    private function distance(Dispatch $dispatch): array
    {
        if ($dispatch->ambulance_id && $dispatch->assigned_at) {
            $points = AmbulanceLocation::query()
                ->where('ambulance_id', $dispatch->ambulance_id)
                ->whereBetween('recorded_at', [$dispatch->assigned_at, $dispatch->completed_at ?? now()])
                ->orderBy('recorded_at')
                ->get(['latitude', 'longitude']);

            $km = 0.0;
            for ($i = 1; $i < $points->count(); $i++) {
                $km += Geo::km($points[$i - 1]->latitude, $points[$i - 1]->longitude, $points[$i]->latitude, $points[$i]->longitude);
            }

            if ($km >= 0.5) {
                return [$km, false];
            }
        }

        $config = config('operations');
        $km = $config['costs']['approach_km_estimate'];

        if ($dispatch->pickup_latitude && $dispatch->hospital) {
            $km += Geo::km($dispatch->pickup_latitude, $dispatch->pickup_longitude, $dispatch->hospital->latitude, $dispatch->hospital->longitude)
                * $config['tracking']['road_factor'];
        }

        return [$km, true];
    }
}
