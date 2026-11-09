<?php

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Enlaces públicos de seguimiento: vencimiento, anulación y datos que muestran.
*/

beforeEach(fn () => config(['app.demo' => false]));

function servicioCompartido(): Dispatch
{
    $hospital = Hospital::create(['name' => 'Hospital de Guápiles', 'short_name' => 'H. Guápiles', 'latitude' => 10.2174, 'longitude' => -83.7896]);
    $unit = Ambulance::create([
        'code' => 'SAR-02', 'plate' => 'AMB-1102', 'type' => AmbulanceType::Basic, 'status' => AmbulanceStatus::EnRoute,
        'latitude' => 10.4100, 'longitude' => -84.0900,
    ]);

    return Dispatch::create([
        'type' => DispatchType::Emergency,
        'priority' => DispatchPriority::High,
        'ambulance_id' => $unit->id,
        'hospital_id' => $hospital->id,
        'patient_name' => 'Ana Mora Solís',
        'pickup_address' => 'Chilamate, 200 m norte de la plaza, casa verde',
        'pickup_latitude' => 10.4470,
        'pickup_longitude' => -84.0780,
    ]);
}

it('nunca envía la dirección escrita del paciente', function () {
    $url = servicioCompartido()->shareTracking();

    $this->getJson("{$url}/estado")
        ->assertOk()
        ->assertJsonPath('pickup.lat', 10.4470)
        ->assertJsonMissingPath('pickup.label');

    $this->get($url)->assertDontSee('casa verde');
});

it('al cerrarse deja de mostrar el punto de atención y el hospital', function () {
    $dispatch = servicioCompartido();
    $url = $dispatch->shareTracking();

    $dispatch->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);
    $this->travel(config('operations.tracking.hours_after_completion') + 1)->hours();

    $this->getJson("{$url}/estado")
        ->assertJsonPath('open', false)
        ->assertJsonPath('pickup', null)
        ->assertJsonPath('hospital', null)
        ->assertJsonPath('unit', null);
});

it('sigue abierto mientras el servicio está en curso y la vida máxima no ha pasado', function () {
    $url = servicioCompartido()->shareTracking();

    $this->travel(config('operations.tracking.max_hours') - 1)->hours();

    $this->getJson("{$url}/estado")->assertJsonPath('open', true);
});

it('vence a las horas máximas aunque nadie cierre el servicio', function () {
    $dispatch = servicioCompartido();
    $url = $dispatch->shareTracking();

    $this->travel(config('operations.tracking.max_hours'))->hours();

    $this->getJson("{$url}/estado")
        ->assertJsonPath('open', false)
        ->assertJsonPath('unit', null)
        ->assertJsonPath('headline', 'Este enlace de seguimiento venció');

    // Volver a compartir crea un enlace nuevo con su propio plazo
    $newUrl = $dispatch->fresh()->shareTracking();
    expect($newUrl)->not->toBe($url);
    $this->getJson("{$newUrl}/estado")->assertJsonPath('open', true);
    $this->getJson("{$url}/estado")->assertNotFound();
});

it('un enlace anulado deja de existir', function () {
    $dispatch = servicioCompartido();
    $url = $dispatch->shareTracking();

    $dispatch->revokeTracking();

    $this->get($url)->assertNotFound();
    $this->getJson("{$url}/estado")->assertNotFound();
});

it('un servicio cancelado no muestra la unidad', function () {
    $dispatch = servicioCompartido();
    $url = $dispatch->shareTracking();

    $dispatch->update(['status' => DispatchStatus::Cancelled, 'cancelled_at' => now()]);

    $this->getJson("{$url}/estado")
        ->assertJsonPath('open', false)
        ->assertJsonPath('unit', null)
        ->assertJsonPath('headline', 'El servicio fue cancelado');
});

it('limita las consultas repetidas al enlace', function () {
    $url = servicioCompartido()->shareTracking();

    foreach (range(1, 60) as $i) {
        $this->getJson("{$url}/estado")->assertOk();
    }

    $this->getJson("{$url}/estado")->assertTooManyRequests();
});
