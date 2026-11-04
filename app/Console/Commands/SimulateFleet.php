<?php

namespace App\Console\Commands;

use App\Enums\AmbulanceStatus;
use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\AmbulanceLocation;
use App\Models\Dispatch;
use App\Models\Hospital;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SimulateFleet extends Command
{
    protected $signature = 'fleet:simulate
        {--tick=2 : Segundos entre cada actualización}
        {--speed=4 : Multiplicador de velocidad para la demo}
        {--auto : Genera emergencias automáticas y asigna la unidad más cercana}
        {--every=3 : Minutos entre emergencias automáticas}';

    protected $description = 'Simula el movimiento de las unidades por las rutas de Sarapiquí';

    /** Bases de operación (cada unidad regresa a la más cercana) */
    private const BASES = [
        [10.4560, -84.0190], // Puerto Viejo
        [10.3930, -84.1230], // La Virgen
    ];

    private const ON_SCENE_SECONDS = 45;
    private const AT_HOSPITAL_SECONDS = 30;
    private const AUTO_ASSIGN_AFTER_SECONDS = 20;

    /** Incidentes ficticios para el modo automático */
    private const INCIDENTS = [
        ['Ruta 4, puente sobre el río Sarapiquí', 10.4555, -84.0090, 'Colisión entre vehículo liviano y motocicleta.'],
        ['La Virgen centro, frente al parque', 10.3925, -84.1215, 'Adulto mayor con dificultad respiratoria.'],
        ['Chilamate, sobre Ruta 4', 10.4470, -84.0780, 'Caída en vía pública, posible fractura de cadera.'],
        ['Puerto Viejo, barrio El Progreso', 10.4600, -84.0130, 'Dolor abdominal intenso de 6 horas de evolución.'],
        ['Ruta 4, cerca de Bajos de Chilamate', 10.4300, -84.0950, 'Volcamiento de vehículo, dos ocupantes.'],
        ['Horquetas centro', 10.3440, -83.9580, 'Menor con convulsión febril.'],
        ['La Virgen, sector Pueblo Nuevo', 10.3990, -84.1150, 'Herida cortante con machete durante jornada agrícola.'],
        ['Puerto Viejo, zona del muelle', 10.4700, -84.0170, 'Persona con síntomas de golpe de calor.'],
    ];

    /** Estado en memoria de cada unidad: ruta actual, destino y tiempos de espera */
    private array $state = [];

    private float $nextAutoAt = 0;

    public function handle(): int
    {
        $this->info('🚑 Simulador de flota iniciado. Presiona Ctrl + C para detenerlo.');
        $this->line('   Velocidad x' . $this->option('speed') . ($this->option('auto') ? ' · Emergencias automáticas cada ' . $this->option('every') . ' min' : ''));

        $this->nextAutoAt = microtime(true) + 15;

        while (true) {
            if ($this->option('auto')) {
                $this->autoDispatch();
            }

            Ambulance::where('is_simulated', true)->get()
                ->each(fn (Ambulance $unit) => $this->step($unit));

            sleep((int) $this->option('tick'));
        }
    }

    /** Avanza una unidad un "paso" según su estado actual */
    private function step(Ambulance $unit): void
    {
        if ($unit->status === AmbulanceStatus::OutOfService) {
            return;
        }

        $s = $this->state[$unit->id] ?? [
            'route' => [],
            'target' => null,
            'wait' => null,
            'base' => $this->nearestBase($unit),
        ];

        $dispatch = Dispatch::with('hospital')
            ->where('ambulance_id', $unit->id)
            ->whereIn('status', [DispatchStatus::Pending, DispatchStatus::Assigned, DispatchStatus::InProgress])
            ->latest('id')
            ->first();

        switch ($unit->status) {
            case AmbulanceStatus::Available:
                if ($dispatch) {
                    if ($dispatch->status === DispatchStatus::Pending) {
                        $dispatch->update(['status' => DispatchStatus::Assigned, 'assigned_at' => now()]);
                    }
                    $this->setStatus($unit, AmbulanceStatus::EnRoute, $dispatch->code);
                    break;
                }
                $this->moveTowards($unit, $s, $s['base'], 'base', 45); // regreso a la base
                break;

            case AmbulanceStatus::EnRoute:
                if (! $dispatch) {
                    $this->setStatus($unit, AmbulanceStatus::Available);
                    break;
                }
                $pickup = [$dispatch->pickup_latitude ?? $unit->latitude, $dispatch->pickup_longitude ?? $unit->longitude];
                if ($this->moveTowards($unit, $s, $pickup, 'pickup-' . $dispatch->id, 75)) {
                    $dispatch->update(['status' => DispatchStatus::InProgress, 'on_scene_at' => now()]);
                    $s['wait'] = time() + self::ON_SCENE_SECONDS;
                    $this->setStatus($unit, AmbulanceStatus::OnScene, $dispatch->code);
                }
                break;

            case AmbulanceStatus::OnScene:
                $unit->speed = 0;
                $s['wait'] ??= time() + self::ON_SCENE_SECONDS;
                if (time() >= $s['wait']) {
                    $s['wait'] = null;
                    if ($dispatch?->hospital) {
                        $this->setStatus($unit, AmbulanceStatus::Transporting, $dispatch->hospital->short_name);
                    } else {
                        $dispatch?->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);
                        $this->setStatus($unit, AmbulanceStatus::Available);
                    }
                }
                break;

            case AmbulanceStatus::Transporting:
                $hospital = $dispatch?->hospital;
                if (! $hospital) {
                    $this->setStatus($unit, AmbulanceStatus::Available);
                    break;
                }
                if ($this->moveTowards($unit, $s, [$hospital->latitude, $hospital->longitude], 'hospital-' . $dispatch->id, 65)) {
                    $s['wait'] = time() + self::AT_HOSPITAL_SECONDS;
                    $this->setStatus($unit, AmbulanceStatus::AtHospital, $hospital->short_name);
                }
                break;

            case AmbulanceStatus::AtHospital:
                $unit->speed = 0;
                $s['wait'] ??= time() + self::AT_HOSPITAL_SECONDS;
                if (time() >= $s['wait']) {
                    $s['wait'] = null;
                    $dispatch?->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);
                    $this->setStatus($unit, AmbulanceStatus::Available, 'servicio completado');
                }
                break;
        }

        $this->state[$unit->id] = $s;
        $unit->last_location_at = now();
        $unit->save();
    }

    /**
     * Mueve la unidad por la ruta hacia el destino.
     * Devuelve true cuando llegó.
     */
    private function moveTowards(Ambulance $unit, array &$s, array $to, string $key, float $kmh): bool
    {
        $from = [(float) $unit->latitude, (float) $unit->longitude];

        if ($this->distance($from, $to) < 25) {
            $unit->speed = 0;
            $s['route'] = [];
            return true;
        }

        if ($s['target'] !== $key || count($s['route']) < 2) {
            $s['target'] = $key;
            $s['route'] = $this->fetchRoute($from, $to);
        }

        $speed = $kmh * (0.85 + mt_rand(0, 30) / 100); // pequeña variación realista
        $meters = $speed / 3.6 * (int) $this->option('tick') * (float) $this->option('speed');

        // Avanzar sobre la ruta la distancia recorrida en este paso
        while ($meters > 0 && count($s['route']) > 1) {
            [$a, $b] = [$s['route'][0], $s['route'][1]];
            $segment = $this->distance($a, $b);

            if ($segment <= $meters) {
                $meters -= $segment;
                array_shift($s['route']);
            } else {
                $f = $meters / $segment;
                $s['route'][0] = [$a[0] + ($b[0] - $a[0]) * $f, $a[1] + ($b[1] - $a[1]) * $f];
                $meters = 0;
            }
        }

        $position = $s['route'][0];
        $next = $s['route'][1] ?? $position;
        $arrived = count($s['route']) < 2;

        $unit->latitude = $position[0];
        $unit->longitude = $position[1];
        $unit->heading = $this->bearing($position, $next);
        $unit->speed = $arrived ? 0 : round($speed);

        AmbulanceLocation::create([
            'ambulance_id' => $unit->id,
            'latitude' => $position[0],
            'longitude' => $position[1],
            'speed' => $unit->speed,
            'heading' => $unit->heading,
            'recorded_at' => now(),
        ]);

        return $arrived;
    }

    /** Pide la ruta por carretera a OSRM; si falla, usa línea recta */
    private function fetchRoute(array $from, array $to): array
    {
        try {
            $url = "https://router.project-osrm.org/route/v1/driving/{$from[1]},{$from[0]};{$to[1]},{$to[0]}";
            $coords = Http::timeout(10)
                ->get($url, ['overview' => 'full', 'geometries' => 'geojson'])
                ->json('routes.0.geometry.coordinates');

            if ($coords) {
                $points = array_map(fn ($c) => [$c[1], $c[0]], $coords);
                return [$from, ...$points, $to];
            }
        } catch (\Throwable) {
            // sin conexión o servicio no disponible
        }

        $this->warn('   ⚠ Ruta por carretera no disponible, usando línea recta.');
        return [$from, $to];
    }

    /** Modo automático: crea emergencias y asigna la unidad disponible más cercana */
    private function autoDispatch(): void
    {
        $pending = Dispatch::where('status', DispatchStatus::Pending)
            ->whereNull('ambulance_id')
            ->whereNotNull('pickup_latitude')
            ->where('created_at', '<=', now()->subSeconds(self::AUTO_ASSIGN_AFTER_SECONDS))
            ->oldest()
            ->get();

        foreach ($pending as $dispatch) {
            $pickup = [$dispatch->pickup_latitude, $dispatch->pickup_longitude];

            $unit = Ambulance::where('is_simulated', true)
                ->where('status', AmbulanceStatus::Available)
                ->get()
                ->sortBy(fn (Ambulance $u) => $this->distance([$u->latitude, $u->longitude], $pickup))
                ->first();

            if (! $unit) {
                break; // no hay unidades libres; se intentará en el siguiente paso
            }

            $dispatch->update([
                'ambulance_id' => $unit->id,
                'status' => DispatchStatus::Assigned,
                'assigned_at' => now(),
            ]);

            $this->line("   📟 {$dispatch->code} asignado a {$unit->code}");
        }

        if (microtime(true) < $this->nextAutoAt) {
            return;
        }

        $this->nextAutoAt = microtime(true) + (float) $this->option('every') * 60;

        [$address, $lat, $lng, $description] = self::INCIDENTS[array_rand(self::INCIDENTS)];
        $priority = collect([DispatchPriority::High, DispatchPriority::High, DispatchPriority::Medium, DispatchPriority::Low])->random();
        $hospital = Hospital::where('short_name', $priority === DispatchPriority::High ? 'H. Guápiles' : 'AS Sarapiquí')->first();
        $age = mt_rand(4, 88);

        $dispatch = Dispatch::create([
            'type' => DispatchType::Emergency,
            'priority' => $priority,
            'status' => DispatchStatus::Pending,
            'hospital_id' => $hospital?->id,
            'patient_name' => (mt_rand(0, 1) ? 'Masculino' : 'Femenina') . ", {$age} años",
            'patient_age' => $age,
            'description' => $description,
            'pickup_address' => $address,
            'pickup_latitude' => $lat + mt_rand(-30, 30) / 10000,
            'pickup_longitude' => $lng + mt_rand(-30, 30) / 10000,
        ]);

        $this->line("   🆘 Nueva emergencia {$dispatch->code}: {$address}");
    }

    private function setStatus(Ambulance $unit, AmbulanceStatus $status, ?string $detail = null): void
    {
        $unit->status = $status;
        $this->line(sprintf('   %s → %s%s', $unit->code, $status->getLabel(), $detail ? " ({$detail})" : ''));
    }

    private function nearestBase(Ambulance $unit): array
    {
        return collect(self::BASES)
            ->sortBy(fn ($base) => $this->distance([$unit->latitude, $unit->longitude], $base))
            ->first();
    }

    /** Distancia en metros entre dos puntos (fórmula de Haversine) */
    private function distance(array $a, array $b): float
    {
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }

    /** Rumbo en grados (0 = norte) */
    private function bearing(array $a, array $b): float
    {
        [$lat1, $lat2] = [deg2rad($a[0]), deg2rad($b[0])];
        $dLng = deg2rad($b[1] - $a[1]);
        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }
}