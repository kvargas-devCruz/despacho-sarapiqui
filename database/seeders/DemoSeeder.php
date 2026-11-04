<?php

namespace Database\Seeders;

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Usuario de demostración ──
        User::updateOrCreate(
            ['email' => 'demo@despacho.cr'],
            ['name' => 'Operador de Despacho', 'password' => 'demo1234'],
        );

        // ── Centros de salud de referencia (coordenadas aproximadas) ──
        $hospitals = collect([
            ['name' => 'Área de Salud Puerto Viejo de Sarapiquí', 'short_name' => 'AS Sarapiquí', 'address' => 'Puerto Viejo, Sarapiquí, Heredia', 'latitude' => 10.4568, 'longitude' => -84.0175],
            ['name' => 'Hospital de Guápiles', 'short_name' => 'H. Guápiles', 'address' => 'Guápiles, Pococí, Limón', 'latitude' => 10.2174, 'longitude' => -83.7896],
            ['name' => 'Hospital San Vicente de Paúl', 'short_name' => 'HSVP', 'address' => 'Heredia centro', 'latitude' => 9.9986, 'longitude' => -84.1103],
            ['name' => 'Hospital San Carlos', 'short_name' => 'H. San Carlos', 'address' => 'Ciudad Quesada, San Carlos, Alajuela', 'latitude' => 10.3233, 'longitude' => -84.4269],
        ])->mapWithKeys(fn ($data) => [
            $data['short_name'] => Hospital::updateOrCreate(['name' => $data['name']], $data),
        ]);

        // ── Unidades (ficticias, controladas por el simulador) ──
        $units = [
            ['code' => 'SAR-01', 'plate' => 'AMB-1101', 'type' => AmbulanceType::Advanced, 'status' => AmbulanceStatus::Available,    'latitude' => 10.4560, 'longitude' => -84.0190],
            ['code' => 'SAR-02', 'plate' => 'AMB-1102', 'type' => AmbulanceType::Basic,    'status' => AmbulanceStatus::EnRoute,      'latitude' => 10.4100, 'longitude' => -84.0900],
            ['code' => 'SAR-03', 'plate' => 'AMB-1103', 'type' => AmbulanceType::Advanced, 'status' => AmbulanceStatus::OnScene,      'latitude' => 10.3440, 'longitude' => -83.9580],
            ['code' => 'SAR-04', 'plate' => 'AMB-1104', 'type' => AmbulanceType::Transfer, 'status' => AmbulanceStatus::Transporting, 'latitude' => 10.3300, 'longitude' => -84.1300],
            ['code' => 'SAR-05', 'plate' => 'AMB-1105', 'type' => AmbulanceType::Basic,    'status' => AmbulanceStatus::Available,    'latitude' => 10.3930, 'longitude' => -84.1230],
            ['code' => 'SAR-06', 'plate' => 'AMB-1106', 'type' => AmbulanceType::Basic,    'status' => AmbulanceStatus::OutOfService, 'latitude' => 10.4575, 'longitude' => -84.0160],
        ];

        $ambulances = collect($units)->mapWithKeys(fn ($data) => [
            $data['code'] => Ambulance::updateOrCreate(
                ['code' => $data['code']],
                $data + ['is_simulated' => true, 'last_location_at' => now()],
            ),
        ]);

        // ── Servicios de ejemplo ──
        $dispatches = [
            [
                'code' => 'SRV-DEMO-001',
                'type' => DispatchType::Emergency,
                'priority' => DispatchPriority::High,
                'status' => DispatchStatus::Assigned,
                'ambulance_id' => $ambulances['SAR-02']->id,
                'hospital_id' => $hospitals['AS Sarapiquí']->id,
                'patient_name' => 'Masculino, identidad pendiente',
                'patient_age' => 34,
                'description' => 'Colisión de motocicleta en Ruta 4. Paciente consciente, posible fractura en extremidad inferior.',
                'pickup_address' => 'Ruta 4, cerca de Chilamate',
                'pickup_latitude' => 10.4470,
                'pickup_longitude' => -84.0780,
                'assigned_at' => now()->subMinutes(6),
            ],
            [
                'code' => 'SRV-DEMO-002',
                'type' => DispatchType::Emergency,
                'priority' => DispatchPriority::High,
                'status' => DispatchStatus::InProgress,
                'ambulance_id' => $ambulances['SAR-03']->id,
                'hospital_id' => $hospitals['H. Guápiles']->id,
                'patient_name' => 'Rafael Mora Solís',
                'patient_age' => 62,
                'description' => 'Dolor torácico de inicio súbito, diaforesis. Antecedente de hipertensión.',
                'pickup_address' => 'Horquetas centro, 200 m norte de la plaza',
                'pickup_latitude' => 10.3440,
                'pickup_longitude' => -83.9580,
                'assigned_at' => now()->subMinutes(22),
                'on_scene_at' => now()->subMinutes(9),
            ],
            [
                'code' => 'SRV-DEMO-003',
                'type' => DispatchType::Transfer,
                'priority' => DispatchPriority::Medium,
                'status' => DispatchStatus::InProgress,
                'ambulance_id' => $ambulances['SAR-04']->id,
                'hospital_id' => $hospitals['HSVP']->id,
                'patient_name' => 'María Fernanda Rojas',
                'patient_age' => 47,
                'description' => 'Traslado programado para valoración por especialista. Paciente estable.',
                'pickup_address' => 'Área de Salud Puerto Viejo de Sarapiquí',
                'pickup_latitude' => 10.4568,
                'pickup_longitude' => -84.0175,
                'assigned_at' => now()->subMinutes(40),
                'on_scene_at' => now()->subMinutes(30),
            ],
            [
                'code' => 'SRV-DEMO-004',
                'type' => DispatchType::Emergency,
                'priority' => DispatchPriority::Medium,
                'status' => DispatchStatus::Pending,
                'patient_name' => 'Femenina, 8 años',
                'patient_age' => 8,
                'description' => 'Caída de altura aproximada de 2 m. Consciente, dolor en brazo derecho.',
                'pickup_address' => 'La Virgen de Sarapiquí, frente a la escuela',
                'pickup_latitude' => 10.3910,
                'pickup_longitude' => -84.1220,
            ],
            [
                'code' => 'SRV-DEMO-005',
                'type' => DispatchType::Transfer,
                'priority' => DispatchPriority::Low,
                'status' => DispatchStatus::Completed,
                'ambulance_id' => $ambulances['SAR-01']->id,
                'hospital_id' => $hospitals['H. San Carlos']->id,
                'patient_name' => 'José Pablo Arias',
                'patient_age' => 71,
                'description' => 'Retorno a domicilio tras alta médica.',
                'pickup_address' => 'Hospital San Carlos',
                'pickup_latitude' => 10.3233,
                'pickup_longitude' => -84.4269,
                'assigned_at' => now()->subHours(5),
                'on_scene_at' => now()->subHours(4)->subMinutes(30),
                'completed_at' => now()->subHours(3),
            ],
        ];

        foreach ($dispatches as $data) {
            Dispatch::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}