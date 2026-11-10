<?php

namespace Database\Seeders;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Hospital;
use App\Models\Patient;
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

        // Pacientes frecuentes para mostrar cómo el sistema los reconoce al llamar
        $frequent = [
            Patient::updateOrCreate(['phone' => '88112233'], ['full_name' => 'Rosa Quirós Vargas', 'address' => 'La Virgen, 300 m sur de la escuela', 'latitude' => 10.3906, 'longitude' => -84.1253, 'care_notes' => 'Usa oxígeno domiciliar. Casa con gradas: llevar silla de traslado.']),
            Patient::updateOrCreate(['phone' => '87004455'], ['full_name' => 'Manuel Arias Brenes', 'address' => 'Puerto Viejo, barrio Los Ángeles', 'latitude' => 10.4590, 'longitude' => -84.0160, 'care_notes' => 'Diabético, hemodiálisis lunes, miércoles y viernes.']),
            Patient::updateOrCreate(['phone' => '86557788'], ['full_name' => 'Lucía Méndez Soto', 'address' => 'Chilamate, frente a la plaza', 'latitude' => 10.4470, 'longitude' => -84.0780, 'care_notes' => null]),
        ];

        for ($i = 1; $i <= 36; $i++) {
            [$place, $lat, $lng, $type, $hospital] = self::PLACES[$i % count(self::PLACES)];
            $isEmergency = $type === 'emergency';

            // Repartidos en los últimos 28 días, varios hoy
            $completedAt = $i <= 4
                ? today()->setTime(6 + $i * 2, ($i * 17) % 60)
                : now()->subDays(($i * 3) % 28 + 1)->setTime(7 + ($i * 5) % 12, ($i * 13) % 60);
            $minutes = $isEmergency ? 45 + ($i * 7) % 40 : 80 + ($i * 11) % 90;
            $priority = $isEmergency ? ($i % 3 === 0 ? DispatchPriority::High : DispatchPriority::Medium) : DispatchPriority::Low;

            // Minutos desde la llamada hasta llegar, casi siempre dentro de la meta
            $toArrive = match ($priority) {
                DispatchPriority::High => 8 + ($i * 3) % 11,
                DispatchPriority::Medium => 12 + ($i * 5) % 24,
                DispatchPriority::Low => 20 + ($i * 7) % 55,
            };
            $createdAt = $completedAt->copy()->subMinutes($minutes);
            $patient = $i % 5 === 0 ? $frequent[($i / 5) % 3] : null;

            $dispatch = Dispatch::firstOrNew(['code' => sprintf('SRV-HIST-%03d', $i)]);

            $dispatch->fill([
                'type' => $isEmergency ? DispatchType::Emergency : DispatchType::Transfer,
                'priority' => $priority,
                'status' => DispatchStatus::Completed,
                'ambulance_id' => $ambulances[$i % $ambulances->count()]->id,
                'hospital_id' => $hospitals[$hospital]?->id,
                'patient_name' => $patient?->full_name ?? 'Paciente de demostración',
                'patient_id' => $patient?->id,
                'caller_phone' => $patient?->phone,
                'description' => $isEmergency ? 'Atención de emergencia (historial de demostración).' : 'Traslado programado (historial de demostración).',
                'pickup_address' => $place,
                'pickup_latitude' => $lat,
                'pickup_longitude' => $lng,
                'assigned_at' => $createdAt->copy()->addMinutes(1 + $i % 4),
                'on_scene_at' => $createdAt->copy()->addMinutes($toArrive),
                'completed_at' => $completedAt,
            ]);

            $dispatch->created_at = $createdAt;

            // El costo se recalcula con las fechas nuevas
            $dispatch->cost_total = null;
            $dispatch->save();
        }
    }
}
