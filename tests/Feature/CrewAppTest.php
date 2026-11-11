<?php

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Enums\UserRole;
use App\Filament\Resources\Ambulances\Pages\ListAmbulances;
use App\Models\Ambulance;
use App\Models\AmbulanceLocation;
use App\Models\Dispatch;
use App\Models\Hospital;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| App de tripulación: enlace privado por unidad, GPS real y pasos del servicio.
*/

beforeEach(fn () => config(['app.demo' => false]));

function unidadConApp(): Ambulance
{
    $unit = Ambulance::create([
        'code' => 'SAR-01', 'plate' => 'AMB-1101', 'type' => AmbulanceType::Basic, 'status' => AmbulanceStatus::Available,
        'latitude' => 10.4560, 'longitude' => -84.0190, 'is_simulated' => true,
    ]);
    $unit->crewUrl();

    return $unit->fresh();
}

function servicioAsignado(Ambulance $unit, bool $hospital = true): Dispatch
{
    return Dispatch::create([
        'type' => DispatchType::Emergency,
        'priority' => DispatchPriority::High,
        'pickup_address' => 'Chilamate, frente a la plaza',
        'pickup_latitude' => 10.4470,
        'pickup_longitude' => -84.0780,
        'patient_name' => 'Lucía Méndez',
        'patient_age' => 67,
        'caller_phone' => '8655-7788',
        'chief_complaint' => 'breathing',
        'triage' => ['conscious' => 1, 'breathing' => 0],
        'ambulance_id' => $unit->id,
        'hospital_id' => $hospital
            ? Hospital::create(['name' => 'Hospital de Guápiles', 'short_name' => 'H. Guápiles', 'latitude' => 10.2150, 'longitude' => -83.7880])->id
            : null,
    ]);
}

it('abre la app con el enlace de la unidad y rechaza enlaces falsos', function () {
    $unit = unidadConApp();

    $this->get("/unidad/{$unit->crew_token}")
        ->assertOk()
        ->assertSee('SAR-01')
        ->assertSee('manifest.webmanifest', false);

    $this->get('/unidad/' . str_repeat('x', 48))->assertNotFound();
    $this->get('/unidad/corto')->assertNotFound();
});

it('muestra el servicio asignado con lo que la tripulación necesita', function () {
    $unit = unidadConApp();
    servicioAsignado($unit);

    $this->getJson("/unidad/{$unit->crew_token}/estado")
        ->assertOk()
        ->assertJsonPath('dispatch.address', 'Chilamate, frente a la plaza')
        ->assertJsonPath('dispatch.patient', 'Lucía Méndez')
        ->assertJsonPath('dispatch.phone', '86557788')
        ->assertJsonPath('dispatch.complaint', 'Dificultad para respirar')
        ->assertJsonPath('dispatch.alerts.0', 'No respira con normalidad')
        ->assertJsonPath('dispatch.hospital.name', 'Hospital de Guápiles')
        ->assertJsonPath('next.key', 'depart');
});

it('sin servicio queda esperando', function () {
    $unit = unidadConApp();

    $this->getJson("/unidad/{$unit->crew_token}/estado")
        ->assertOk()
        ->assertJsonPath('dispatch', null)
        ->assertJsonPath('next', null);
});

it('el GPS del celular mueve la unidad y la saca del simulador', function () {
    $unit = unidadConApp();

    $this->postJson("/unidad/{$unit->crew_token}/ubicacion", [
        'latitude' => 10.4521, 'longitude' => -84.0305, 'speed' => 12.5, 'heading' => 90, 'accuracy' => 8,
    ])->assertOk();

    $unit->refresh();
    expect($unit->latitude)->toBe(10.4521)
        ->and($unit->speed)->toBe(45.0)          // 12,5 m/s = 45 km/h
        ->and($unit->is_simulated)->toBeFalse()
        ->and($unit->hasLiveGps())->toBeTrue()
        ->and(AmbulanceLocation::where('ambulance_id', $unit->id)->count())->toBe(1);

    $this->postJson("/unidad/{$unit->crew_token}/ubicacion", ['latitude' => 40.4, 'longitude' => -3.7])
        ->assertUnprocessable();
});

it('recorre los pasos del servicio con hospital y guarda horas y bitácora', function () {
    $unit = unidadConApp();
    $dispatch = servicioAsignado($unit);
    $url = "/unidad/{$unit->crew_token}/paso";

    foreach (['depart' => AmbulanceStatus::EnRoute, 'arrive' => AmbulanceStatus::OnScene, 'transport' => AmbulanceStatus::Transporting, 'hospital' => AmbulanceStatus::AtHospital] as $step => $status) {
        $this->postJson($url, ['dispatch' => $dispatch->id, 'step' => $step])->assertOk();
        expect($unit->fresh()->status)->toBe($status);
    }

    expect($dispatch->fresh()->status)->toBe(DispatchStatus::InProgress)
        ->and($dispatch->fresh()->on_scene_at)->not->toBeNull();

    $this->postJson($url, ['dispatch' => $dispatch->id, 'step' => 'finish'])
        ->assertOk()
        ->assertJsonPath('dispatch', null);

    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Completed)
        ->and($dispatch->fresh()->completed_at)->not->toBeNull()
        ->and($unit->fresh()->status)->toBe(AmbulanceStatus::Available)
        ->and($dispatch->events()->pluck('note')->filter()->implode(' | '))
        ->toContain('Tripulación: en camino a la escena')
        ->toContain('Tripulación: en la escena')
        ->toContain('Tripulación: trasladando al paciente')
        ->toContain('Tripulación: servicio terminado');
});

it('sin hospital de destino se termina en la escena', function () {
    $unit = unidadConApp();
    $dispatch = servicioAsignado($unit, hospital: false);
    $url = "/unidad/{$unit->crew_token}/paso";

    $this->postJson($url, ['dispatch' => $dispatch->id, 'step' => 'depart'])->assertOk();
    $this->postJson($url, ['dispatch' => $dispatch->id, 'step' => 'arrive'])->assertOk()->assertJsonPath('next.key', 'finish');
    $this->postJson($url, ['dispatch' => $dispatch->id, 'step' => 'finish'])->assertOk();

    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Completed);
});

it('un doble toque no salta pasos', function () {
    $unit = unidadConApp();
    $dispatch = servicioAsignado($unit);
    $url = "/unidad/{$unit->crew_token}/paso";

    $this->postJson($url, ['dispatch' => $dispatch->id, 'step' => 'depart'])->assertOk();
    $this->postJson($url, ['dispatch' => $dispatch->id, 'step' => 'depart'])
        ->assertStatus(409)
        ->assertJsonPath('next.key', 'arrive');

    expect($unit->fresh()->status)->toBe(AmbulanceStatus::EnRoute);
});

it('una unidad no puede mover el servicio de otra', function () {
    $mine = unidadConApp();
    $other = Ambulance::create(['code' => 'SAR-02', 'plate' => 'AMB-1102', 'type' => AmbulanceType::Basic, 'status' => AmbulanceStatus::Available]);
    $dispatch = servicioAsignado($other);

    $this->postJson("/unidad/{$mine->crew_token}/paso", ['dispatch' => $dispatch->id, 'step' => 'depart'])->assertStatus(409);

    expect($other->fresh()->status)->toBe(AmbulanceStatus::Available);
});

it('un enlace nuevo anula el anterior', function () {
    $unit = unidadConApp();
    $old = $unit->crew_token;

    $unit->rotateCrewToken();

    $this->get("/unidad/{$old}")->assertNotFound();
    $this->get("/unidad/{$unit->fresh()->crew_token}")->assertOk();
});

it('se instala como app con el enlace de la unidad', function () {
    $unit = unidadConApp();

    $this->getJson("/unidad/{$unit->crew_token}/manifest.webmanifest")
        ->assertOk()
        ->assertJsonPath('start_url', "/unidad/{$unit->crew_token}")
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('short_name', 'SAR-01');

    $this->get('/crew-sw.js')->assertStatus(404); // lo sirve el servidor web, no Laravel
    expect(file_exists(public_path('crew-sw.js')))->toBeTrue()
        ->and(file_exists(public_path('images/app-icon-512.png')))->toBeTrue();
});

it('el código QR lo ven administración y supervisión, no consulta', function () {
    $unit = unidadConApp();

    $this->actingAs(User::factory()->create(['role' => UserRole::Supervisor]));
    Livewire::test(ListAmbulances::class)
        ->assertActionVisible(TestAction::make('crew')->table($unit))
        ->mountAction(TestAction::make('crew')->table($unit))
        ->assertMountedActionModalSee('Escanee el código');

    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));
    Livewire::test(ListAmbulances::class)->assertActionHidden(TestAction::make('crew')->table($unit));
});

it('el enlace nunca viaja en los datos de la unidad', function () {
    $unit = unidadConApp();

    expect($unit->toArray())->not->toHaveKey('crew_token');
});
