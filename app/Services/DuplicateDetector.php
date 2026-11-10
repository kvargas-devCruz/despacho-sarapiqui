<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\Patient;
use Illuminate\Support\Collection;

/**
 * Avisa de posibles solicitudes duplicadas.
 *
 * Es común que por una misma emergencia llamen varias personas (el vecino,
 * el familiar, el que pasaba). Antes de crear el servicio se buscan otros
 * activos de los últimos minutos con el mismo teléfono, el mismo paciente o
 * muy cerca del mismo lugar. No bloquea: el despachador decide.
 */
class DuplicateDetector
{
    /**
     * @param  array{caller_phone?: ?string, patient_id?: ?int, pickup_latitude?: mixed, pickup_longitude?: mixed}  $data
     * @return Collection<int, array{dispatch: Dispatch, reason: string}>
     */
    public function find(array $data, ?Dispatch $except = null): Collection
    {
        $phone = Patient::normalizePhone($data['caller_phone'] ?? null);
        $patientId = $data['patient_id'] ?? null;
        $lat = is_numeric($data['pickup_latitude'] ?? null) ? (float) $data['pickup_latitude'] : null;
        $lng = is_numeric($data['pickup_longitude'] ?? null) ? (float) $data['pickup_longitude'] : null;

        if (! $phone && ! $patientId && ($lat === null || $lng === null)) {
            return collect();
        }

        $config = config('operations.duplicates');

        return Dispatch::query()
            ->whereIn('status', UnitAvailability::ACTIVE_STATUSES)
            ->where('created_at', '>=', now()->subMinutes($config['minutes']))
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->latest()
            ->get()
            ->map(function (Dispatch $dispatch) use ($phone, $patientId, $lat, $lng, $config) {
                $reason = match (true) {
                    $phone && $dispatch->caller_phone === $phone => 'mismo teléfono',
                    $patientId && $dispatch->patient_id === (int) $patientId => 'mismo paciente',
                    $lat !== null && $lng !== null && $dispatch->pickup_latitude !== null
                        && Geo::km($lat, $lng, (float) $dispatch->pickup_latitude, (float) $dispatch->pickup_longitude) * 1000 <= $config['meters'] => 'a menos de ' . $config['meters'] . ' m',
                    default => null,
                };

                return $reason ? ['dispatch' => $dispatch, 'reason' => $reason] : null;
            })
            ->filter()
            ->values();
    }

    /** Texto corto para mostrar en el formulario, o null si no hay duplicados */
    public function warning(array $data, ?Dispatch $except = null): ?string
    {
        $matches = $this->find($data, $except);

        if ($matches->isEmpty()) {
            return null;
        }

        return 'Posible solicitud duplicada: ' . $matches
            ->map(fn (array $match) => "{$match['dispatch']->code} ({$match['reason']}, hace {$match['dispatch']->created_at->diffForHumans(syntax: true)})")
            ->join('; ') . '. Revise antes de crear otro servicio.';
    }
}
