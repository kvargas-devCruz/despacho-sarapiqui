<?php

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Sin el simulador de la demo, para que las pruebas no dependan del tiempo ni de internet
beforeEach(fn () => config(['app.demo' => false]));

function servicioEnCamino(array $attributes = []): Dispatch
{
    $hospital = Hospital::create(['name' => 'Hospital de Guápiles', 'short_name' => 'H. Guápiles', 'latitude' => 10.2174, 'longitude' => -83.7896]);
    $unit = Ambulance::create([
        'code' => 'SAR-02', 'plate' => 'AMB-1102', 'type' => AmbulanceType::Basic, 'status' => AmbulanceStatus::EnRoute,
        'latitude' => 10.4100, 'longitude' => -84.0900,
    ]);

    return Dispatch::create(array_merge([
        'type' => DispatchType::Emergency,
        'priority' => DispatchPriority::High,
        'status' => DispatchStatus::Assigned,
        'ambulance_id' => $unit->id,
        'hospital_id' => $hospital->id,
        'patient_name' => 'Ana Mora Solís',
        'pickup_address' => 'Chilamate, Ruta 4',
        'pickup_latitude' => 10.4470,
        'pickup_longitude' => -84.0780,
        'assigned_at' => now()->subMinutes(10),
    ], $attributes));
}

it('comparte el seguimiento sin mostrar datos del paciente', function () {
    $dispatch = servicioEnCamino();
    $url = $dispatch->shareTracking();

    expect($url)->toContain('/seguimiento/')
        ->and($dispatch->shareTracking())->toBe($url);

    $this->get($url)
        ->assertOk()
        ->assertSee('La unidad SAR-02 va en camino')
        ->assertSee($dispatch->code)
        ->assertDontSee('Ana Mora Solís');

    $this->getJson("{$url}/estado")
        ->assertOk()
        ->assertJsonPath('step', 1)
        ->assertJsonPath('unit.code', 'SAR-02')
        ->assertJsonPath('eta.target', 'al sitio')
        ->assertJsonMissing(['patient_name' => 'Ana Mora Solís']);
});

it('deja de mostrar la unidad cuando termina el servicio', function () {
    $dispatch = servicioEnCamino();
    $url = $dispatch->shareTracking();

    $dispatch->update(['status' => DispatchStatus::Completed, 'completed_at' => now()->subHours(3)]);

    $this->getJson("{$url}/estado")
        ->assertJsonPath('open', false)
        ->assertJsonPath('unit', null)
        ->assertJsonPath('headline', 'Servicio finalizado');

    $this->get('/seguimiento/token-que-no-existe')->assertNotFound();
});

it('calcula el costo al completar el servicio', function () {
    $dispatch = servicioEnCamino(['assigned_at' => now()->subMinutes(60)]);

    $dispatch->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);
    $dispatch->refresh();

    $expectedFuel = $dispatch->distance_km / 7 * 760;

    expect($dispatch->cost_total)->toBeGreaterThan(0)
        ->and($dispatch->distance_estimated)->toBeTrue()
        ->and($dispatch->duration_minutes)->toBe(60)
        ->and($dispatch->cost_crew)->toEqual(9500.0)
        ->and($dispatch->cost_supplies)->toEqual(18000.0)
        ->and(abs($dispatch->cost_fuel - $expectedFuel))->toBeLessThan(1)
        ->and($dispatch->cost_total)->toEqual($dispatch->cost_fuel + $dispatch->cost_crew + $dispatch->cost_maintenance + $dispatch->cost_supplies)
        ->and($dispatch->margin())->toEqual($dispatch->reference_charge - $dispatch->cost_total);
});

it('usa el recorrido real de la unidad cuando existe', function () {
    $dispatch = servicioEnCamino(['assigned_at' => now()->subMinutes(30)]);

    foreach ([[10.4100, -84.0900], [10.4300, -84.0850], [10.4470, -84.0780]] as $i => [$lat, $lng]) {
        $dispatch->ambulance->locations()->create(['latitude' => $lat, 'longitude' => $lng, 'recorded_at' => now()->subMinutes(20 - $i * 5)]);
    }

    $dispatch->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);

    expect($dispatch->fresh()->distance_estimated)->toBeFalse()
        ->and($dispatch->fresh()->distance_km)->toBeBetween(3.5, 5.0);
});

it('muestra los costos en el escritorio', function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::first());

    expect(Dispatch::whereNotNull('cost_total')->count())->toBeGreaterThan(30);

    $this->get('/admin')->assertOk();
    $this->get('/admin/dispatches')->assertOk();
});
