<?php

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Filament\Resources\Dispatches\Pages\CreateDispatch;
use App\Filament\Resources\Dispatches\Pages\EditDispatch;
use App\Models\Dispatch;
use App\Models\Hospital;
use App\Models\User;
use App\Services\Triage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Recepción guiada: guion por pasos, prioridad sugerida y punto confirmado en el mapa.
*/

beforeEach(fn () => config(['app.demo' => false]));

it('sugiere la prioridad más alta entre el motivo y las respuestas', function () {
    $triage = app(Triage::class);

    $calm = $triage->evaluate('illness', ['conscious' => 1, 'breathing' => 1]);
    expect($calm['priority'])->toBe(DispatchPriority::Low);

    $critical = $triage->evaluate('illness', ['conscious' => 1, 'breathing' => 0]);
    expect($critical['priority'])->toBe(DispatchPriority::High)
        ->and($critical['reasons'])->toContain('no respira con normalidad');

    // Un adulto mayor con malestar no queda en prioridad baja
    expect($triage->evaluate('illness', [], 80)['priority'])->toBe(DispatchPriority::Medium);

    // El peligro en la escena no cambia la prioridad pero avisa
    $danger = $triage->evaluate('trauma', ['scene_danger' => 1]);
    expect($danger['priority'])->toBe(DispatchPriority::Medium)
        ->and($danger['warnings'][0])->toContain('9-1-1');
});

it('al responder el guion propone la prioridad en el formulario', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateDispatch::class)
        ->fillForm(['type' => DispatchType::Emergency, 'chief_complaint' => 'illness'])
        ->assertSchemaStateSet(['priority' => DispatchPriority::Low])
        ->fillForm(['triage.breathing' => 0])
        ->assertSchemaStateSet(['priority' => DispatchPriority::High])
        ->assertSee('Prioridad sugerida: Alta')
        ->assertSee('no respira con normalidad');
});

it('una emergencia no se crea sin guion, sin pin ni confirmación del punto', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'type' => DispatchType::Emergency,
            'priority' => DispatchPriority::High,
            'status' => DispatchStatus::Pending,
            'pickup_address' => 'La Virgen centro',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'caller_phone', 'chief_complaint', 'triage.conscious', 'triage.breathing',
            'pickup_latitude', 'pickup_longitude', 'location_confirmed',
        ]);

    expect(Dispatch::count())->toBe(0);
});

it('rechaza un punto fuera de Costa Rica', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateDispatch::class)
        ->fillForm(guionCompleto([
            'type' => DispatchType::Emergency,
            'priority' => DispatchPriority::Low,
            'status' => DispatchStatus::Pending,
            'pickup_address' => 'Algún lugar',
            'pickup_latitude' => 40.4,
            'pickup_longitude' => -3.7,
        ]))
        ->call('create')
        ->assertHasFormErrors(['pickup_latitude', 'pickup_longitude']);
});

it('bajar la prioridad sugerida obliga a anotar por qué y queda guardado', function () {
    $this->actingAs(User::factory()->create());

    $form = Livewire::test(CreateDispatch::class)
        ->fillForm(guionCompleto([
            'type' => DispatchType::Emergency,
            'status' => DispatchStatus::Pending,
            'pickup_address' => 'Puerto Viejo, frente al banco',
            'chief_complaint' => 'chest_pain',
        ]))
        ->fillForm(['priority' => DispatchPriority::Medium])
        ->call('create')
        ->assertHasFormErrors(['priority_note']);

    $form->fillForm(['priority_note' => 'Dolor leve desde ayer, ya valorado por el médico de la familia'])
        ->call('create')
        ->assertHasNoFormErrors();

    $dispatch = Dispatch::first();
    expect($dispatch->priority)->toBe(DispatchPriority::Medium)
        ->and($dispatch->suggested_priority)->toBe(DispatchPriority::High)
        ->and($dispatch->chief_complaint)->toBe('chest_pain')
        ->and((bool) $dispatch->triage['breathing'])->toBeTrue()
        ->and($dispatch->location_confirmed)->toBeTrue()
        ->and($dispatch->priority_note)->toContain('Dolor leve');
});

it('un traslado pide paciente y destino, no las preguntas de emergencia', function () {
    $this->actingAs(User::factory()->create());
    $hospital = Hospital::create(['name' => 'Hospital San Vicente de Paúl', 'latitude' => 10.0, 'longitude' => -84.1]);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'type' => DispatchType::Transfer,
            'priority' => DispatchPriority::Low,
            'status' => DispatchStatus::Pending,
            'pickup_address' => 'Clínica de Puerto Viejo',
        ])
        ->call('create')
        ->assertHasFormErrors(['patient_name', 'hospital_id'])
        ->assertHasNoFormErrors(['chief_complaint', 'triage.conscious', 'pickup_latitude', 'location_confirmed'])
        ->fillForm(['patient_name' => 'María Solano', 'hospital_id' => $hospital->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Dispatch::first()->type)->toBe(DispatchType::Transfer);
});

it('los servicios viejos se editan sin pedir el guion', function () {
    $this->actingAs(User::factory()->create());
    $old = Dispatch::create([
        'type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'Chilamate',
    ]);

    Livewire::test(EditDispatch::class, ['record' => $old->getRouteKey()])
        ->fillForm(['description' => 'Paciente estable'])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('el formulario muestra el mapa para marcar el punto', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/dispatches/create')
        ->assertOk()
        ->assertSee('Buscar la dirección en el mapa')
        ->assertSee('Confirmé el punto con quien llama');
});
