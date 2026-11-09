<?php

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Enums\ScheduledTransferStatus;
use App\Models\Ambulance;
use App\Models\Hospital;
use App\Models\ScheduledTransfer;
use App\Models\User;
use App\Services\TransferSchedule;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.demo' => false]);
    Carbon::setTestNow('2026-11-09 08:00');
});

afterEach(fn () => Carbon::setTestNow());

function cita(array $attributes = []): ScheduledTransfer
{
    $hospital = Hospital::firstOrCreate(['name' => 'Área de Salud Sarapiquí'], ['short_name' => 'AS Sarapiquí', 'latitude' => 10.4568, 'longitude' => -84.0175]);

    return ScheduledTransfer::create(array_merge([
        'patient_name' => 'Rosa Quirós',
        'pickup_address' => 'Puerto Viejo',
        'pickup_latitude' => 10.4547,
        'pickup_longitude' => -84.0177,
        'hospital_id' => $hospital->id,
        'appointment_at' => '2026-11-10 10:00',
        'ambulance_type' => AmbulanceType::Transfer,
    ], $attributes));
}

function unidadDeTraslado(string $code = 'SAR-04'): Ambulance
{
    return Ambulance::create(['code' => $code, 'plate' => "AMB-{$code}", 'type' => AmbulanceType::Transfer, 'status' => AmbulanceStatus::Available]);
}

it('calcula la hora de recogida y el tiempo ocupado', function () {
    $oneWay = cita();
    $roundTrip = cita(['patient_name' => 'Otro paciente', 'round_trip' => true]);

    expect($oneWay->pickup_at->format('H:i'))->toBe('09:30')
        ->and($oneWay->busy_until->format('H:i'))->toBe('10:20')
        ->and($roundTrip->busy_until->format('H:i'))->toBe('11:41');

    // Si cambia la hora de la cita, la recogida se recalcula
    $oneWay->update(['appointment_at' => '2026-11-10 14:00']);
    expect($oneWay->fresh()->pickup_at->format('H:i'))->toBe('13:30');
});

it('avisa los choques de horario', function () {
    $unit = unidadDeTraslado();
    cita(['ambulance_id' => $unit->id]);
    $clash = cita(['patient_name' => 'Carlos Brenes', 'appointment_at' => '2026-11-10 10:10', 'ambulance_id' => $unit->id]);
    $samePatient = cita(['appointment_at' => '2026-11-10 16:00']);

    $warnings = app(TransferSchedule::class)->conflicts($clash);

    expect(collect($warnings)->join(' '))->toContain('La unidad ya tiene el traslado de Rosa Quirós')
        ->toContain('solo hay 1 unidad disponible')
        ->and(app(TransferSchedule::class)->conflicts($samePatient))->toContain('El paciente ya tiene otro traslado ese mismo día.');
});

it('agenda una serie semanal', function () {
    $series = app(TransferSchedule::class)->createSeries([
        'patient_name' => 'Rosa Quirós',
        'pickup_address' => 'Puerto Viejo',
        'hospital_id' => cita()->hospital_id,
        'appointment_at' => '2026-11-16 07:00', // lunes
        'round_trip' => true,
        'ambulance_type' => AmbulanceType::Transfer,
    ], [1, 3, 5], Carbon::parse('2026-11-27'));

    expect($series)->toHaveCount(6)
        ->and($series->pluck('appointment_at')->map->format('D')->unique()->values()->all())->toBe(['Mon', 'Wed', 'Fri'])
        ->and($series->pluck('series_id')->unique())->toHaveCount(1);
});

it('convierte la cita en servicio del despacho', function () {
    $unit = unidadDeTraslado();
    $transfer = cita(['ambulance_id' => $unit->id]);

    $dispatch = app(TransferSchedule::class)->dispatch($transfer);

    expect($dispatch->type)->toBe(DispatchType::Transfer)
        ->and($dispatch->status)->toBe(DispatchStatus::Assigned)
        ->and($dispatch->ambulance_id)->toBe($unit->id)
        ->and($transfer->fresh()->status)->toBe(ScheduledTransferStatus::Dispatched)
        ->and($transfer->fresh()->dispatch_id)->toBe($dispatch->id);

    app(TransferSchedule::class)->dispatch($transfer->fresh());
})->throws(ValidationException::class);

it('abre la agenda y carga la demostración sin duplicar', function () {
    Carbon::setTestNow();
    $this->seed(DemoSeeder::class);
    $count = ScheduledTransfer::count();
    $this->seed(DemoSeeder::class);

    expect($count)->toBeGreaterThan(8)->and(ScheduledTransfer::count())->toBe($count);

    $this->actingAs(User::first());
    $this->get('/admin/scheduled-transfers')->assertOk()->assertSee('Rosa Elena Quirós');
});
