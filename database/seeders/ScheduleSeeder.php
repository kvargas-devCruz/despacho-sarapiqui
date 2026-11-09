<?php

namespace Database\Seeders;

use App\Enums\AmbulanceType;
use App\Enums\ScheduledTransferStatus;
use App\Models\Ambulance;
use App\Models\Hospital;
use App\Models\ScheduledTransfer;
use App\Services\TransferSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Agenda de demostración para la próxima semana (pacientes ficticios):
 * una serie de hemodiálisis, controles y terapias, con un par de choques
 * de horario para que el sistema los señale.
 */
class ScheduleSeeder extends Seeder
{
    public function run(TransferSchedule $schedule): void
    {
        if (ScheduledTransfer::exists()) {
            return;
        }

        $hospital = Hospital::all()->keyBy('short_name');
        $transferUnit = Ambulance::where('type', AmbulanceType::Transfer)->first();
        $at = fn (int $days, string $time) => today()->addDays($days)->setTimeFromTimeString($time);

        // Hemodiálisis lunes, miércoles y viernes durante dos semanas
        $nextMonday = today()->next(Carbon::MONDAY);
        $schedule->createSeries([
            'patient_name' => 'Rosa Elena Quirós',
            'patient_national_id' => '4-0123-0456',
            'patient_phone' => '8812-3344',
            'reason' => 'Hemodiálisis',
            'pickup_address' => 'Puerto Viejo, barrio El Progreso, casa esquinera verde',
            'pickup_latitude' => 10.4600,
            'pickup_longitude' => -84.0130,
            'hospital_id' => $hospital['H. Guápiles']->id,
            'appointment_at' => $nextMonday->copy()->setTime(7, 0),
            'round_trip' => true,
            'ambulance_type' => AmbulanceType::Transfer,
            'ambulance_id' => $transferUnit?->id,
            'status' => ScheduledTransferStatus::Confirmed,
            'notes' => 'Paciente en silla de ruedas.',
        ], [1, 3, 5], $nextMonday->copy()->addDays(13));

        // [días, cita, paciente, motivo, dirección, lat, lng, hospital, regreso, unidad asignada, estado]
        $items = [
            [0, '15:30', 'Manuel Arias Solano', 'Control de cardiología', 'La Virgen, sector Pueblo Nuevo', 10.3990, -84.1150, 'HSVP', true, false, ScheduledTransferStatus::Confirmed],
            [1, '08:00', 'Ana Lucía Méndez', 'Fisioterapia', 'Chilamate, 200 m oeste de la escuela', 10.4470, -84.0780, 'AS Sarapiquí', true, true, ScheduledTransferStatus::Scheduled],
            [1, '09:00', 'Carlos Brenes Mora', 'Control de oncología', 'Horquetas centro', 10.3440, -83.9580, 'HSVP', false, true, ScheduledTransferStatus::Scheduled],
            [2, '10:30', 'Elena Vargas Rojas', 'Ultrasonido', 'Puerto Viejo centro', 10.4560, -84.0150, 'H. San Carlos', false, false, ScheduledTransferStatus::Scheduled],
            [3, '13:00', 'José Pablo Arias', 'Retiro de yeso', 'Río Frío centro', 10.3300, -83.8900, 'H. Guápiles', true, false, ScheduledTransferStatus::Confirmed],
            [4, '06:30', 'Marta Solís Ureña', 'Cirugía ambulatoria', 'La Virgen, frente al parque', 10.3925, -84.1215, 'HSVP', false, false, ScheduledTransferStatus::Scheduled],
        ];

        foreach ($items as [$days, $time, $name, $reason, $address, $lat, $lng, $short, $roundTrip, $assign, $status]) {
            ScheduledTransfer::create([
                'patient_name' => $name,
                'reason' => $reason,
                'pickup_address' => $address,
                'pickup_latitude' => $lat,
                'pickup_longitude' => $lng,
                'hospital_id' => $hospital[$short]->id,
                'appointment_at' => $at($days, $time),
                'round_trip' => $roundTrip,
                'ambulance_type' => AmbulanceType::Transfer,
                'ambulance_id' => $assign ? $transferUnit?->id : null,
                'status' => $status,
            ]);
        }
    }
}
