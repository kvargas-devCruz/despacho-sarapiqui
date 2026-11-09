<?php

namespace Database\Seeders;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Hospital;
use Illuminate\Database\Seeder;

/**
 * Historial de servicios completados del último mes (datos ficticios),
 * para que el escritorio muestre costos y márgenes desde el primer día.
 * Se puede ejecutar varias veces sin duplicar.
 */
class DispatchHistorySeeder extends Seeder
{
    /** [lugar, latitud, longitud, tipo, hospital] */
    private const PLACES = [
        ['Puerto Viejo centro', 10.4560, -84.0150, 'emergency', 'AS Sarapiquí'],
        ['La Virgen, frente al parque', 10.3925, -84.1215, 'emergency', 'AS Sarapiquí'],
        ['Horquetas centro', 10.3440, -83.9580, 'emergency', 'H. Guápiles'],
        ['Chilamate, Ruta 4', 10.4470, -84.0780, 'emergency', 'AS Sarapiquí'],
        ['Puerto Viejo, barrio El Progreso', 10.4600, -84.0130, 'transfer', 'HSVP'],
        ['La Virgen, Pueblo Nuevo', 10.3990, -84.1150, 'transfer', 'H. San Carlos'],
        ['Área de Salud Sarapiquí', 10.4568, -84.0175, 'transfer', 'H. Guápiles'],
        ['Río Frío, centro', 10.3300, -83.8900, 'emergency', 'H. Guápiles'],
    ];

    public function run(): void
    {
        $ambulances = Ambulance::orderBy('code')->get();
        $hospitals = Hospital::all()->keyBy('short_name');

        if ($ambulances->isEmpty() || $hospitals->isEmpty()) {
            return;
        }

        for ($i = 1; $i <= 36; $i++) {
            [$place, $lat, $lng, $type, $hospital] = self::PLACES[$i % count(self::PLACES)];
            $isEmergency = $type === 'emergency';

            // Repartidos en los últimos 28 días, varios hoy
            $completedAt = $i <= 4
                ? today()->setTime(6 + $i * 2, ($i * 17) % 60)
                : now()->subDays(($i * 3) % 28 + 1)->setTime(7 + ($i * 5) % 12, ($i * 13) % 60);
            $minutes = $isEmergency ? 45 + ($i * 7) % 40 : 80 + ($i * 11) % 90;

            $dispatch = Dispatch::firstOrNew(['code' => sprintf('SRV-HIST-%03d', $i)]);

            $dispatch->fill([
                'type' => $isEmergency ? DispatchType::Emergency : DispatchType::Transfer,
                'priority' => $isEmergency ? ($i % 3 === 0 ? DispatchPriority::High : DispatchPriority::Medium) : DispatchPriority::Low,
                'status' => DispatchStatus::Completed,
                'ambulance_id' => $ambulances[$i % $ambulances->count()]->id,
                'hospital_id' => $hospitals[$hospital]?->id,
                'patient_name' => 'Paciente de demostración',
                'description' => $isEmergency ? 'Atención de emergencia (historial de demostración).' : 'Traslado programado (historial de demostración).',
                'pickup_address' => $place,
                'pickup_latitude' => $lat,
                'pickup_longitude' => $lng,
                'assigned_at' => $completedAt->copy()->subMinutes($minutes),
                'on_scene_at' => $completedAt->copy()->subMinutes((int) ($minutes * 0.6)),
                'completed_at' => $completedAt,
            ]);

            if (! $dispatch->exists) {
                $dispatch->created_at = $completedAt->copy()->subMinutes($minutes + 3);
            }

            // El costo se recalcula con las fechas nuevas
            $dispatch->cost_total = null;
            $dispatch->save();
        }
    }
}
