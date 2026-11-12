<?php

namespace App\Services\Transfers;

use App\Mail\PatientIncoming;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Services\Routing\RouteProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa al hospital que va un paciente en camino: unidad, prioridad, motivo,
 * alertas del guion, hora estimada y enlace para seguir la ambulancia.
 * Si el hospital no tiene correo, queda anotado en la bitácora.
 */
class HospitalNotifier
{
    /** Velocidad promedio de una ambulancia en ruta (km/h) para la hora estimada */
    private const AVERAGE_KMH = 55;

    public function __construct(private RouteProvider $routes) {}

    public function patientOnTheWay(Dispatch $dispatch, Ambulance $unit): void
    {
        $hospital = $dispatch->hospital;

        if (! $hospital) {
            return;
        }

        if (blank($hospital->email)) {
            $dispatch->logStatus("Sin correo del hospital {$hospital->name}: avisar por teléfono" . ($hospital->phone ? " ({$hospital->phone})" : ''));

            return;
        }

        $minutes = $this->etaMinutes($unit, $hospital->latitude, $hospital->longitude);

        try {
            Mail::to($hospital->email)->send(new PatientIncoming($dispatch, $unit, $minutes, $dispatch->shareTracking()));
        } catch (Throwable $e) {
            Log::warning('Aviso al hospital no enviado', ['dispatch' => $dispatch->code, 'error' => $e->getMessage()]);
            $dispatch->logStatus("No se pudo enviar el aviso a {$hospital->name}: avisar por teléfono");

            return;
        }

        $dispatch->forceFill(['hospital_notified_at' => now()])->saveQuietly();
        $dispatch->logStatus("Aviso enviado a {$hospital->name}" . ($minutes ? ", llegada estimada en {$minutes} min" : ''));
    }

    public function etaMinutes(Ambulance $unit, ?float $lat, ?float $lng): ?int
    {
        if (! $unit->latitude || ! $lat) {
            return null;
        }

        try {
            $points = $this->routes->route([$unit->latitude, $unit->longitude], [$lat, $lng])->points;
        } catch (Throwable) {
            $points = [[$unit->latitude, $unit->longitude], [$lat, $lng]];
        }

        $km = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $km += self::km($points[$i - 1], $points[$i]);
        }

        // Si la ruta es solo la línea recta, se corrige por las curvas de la carretera
        if (count($points) <= 2) {
            $km *= 1.35;
        }

        return max(1, (int) round($km / self::AVERAGE_KMH * 60));
    }

    /** @param  array{0: float, 1: float}  $a */
    private static function km(array $a, array $b): float
    {
        $r = 6371;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($h));
    }
}
