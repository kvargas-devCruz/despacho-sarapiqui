<?php

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Filament\Resources\Dispatches\Pages\CreateDispatch;
use App\Filament\Resources\Dispatches\Pages\EditDispatch;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\User;
use App\Services\FleetSimulator;
use App\Services\UnitAvailability;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Una ambulancia no se puede asignar a dos servicios al mismo tiempo.
*/

beforeEach(fn () => config(['app.demo' => false]));

function unidad(string $code = 'SAR-01', AmbulanceStatus $status = AmbulanceStatus::Available): Ambulance
{
    return Ambulance::create([
        'code' => $code, 'plate' => "AMB-{$code}", 'type' => AmbulanceType::Basic, 'status' => $status,
        'latitude' => 10.4560, 'longitude' => -84.0190,
    ]);
}

function servicio(array $attributes = []): Dispatch
{
    return Dispatch::create(array_merge([
        'type' => DispatchType::Emergency,
        'priority' => DispatchPriority::High,
        'pickup_address' => 'Chilamate, Ruta 4',
        'pickup_latitude' => 10.4470,
        'pickup_longitude' => -84.0780,
    ], $attributes));
}

it('al asignar una unidad el servicio pasa a asignado con su hora', function () {
    $dispatch = servicio();

    app(UnitAvailability::class)->assign($dispatch, unidad());

    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Assigned)
        ->and($dispatch->fresh()->assigned_at)->not->toBeNull();
});

it('no asigna una unidad que ya atiende otro servicio', function () {
    $unit = unidad();
    $first = servicio(['ambulance_id' => $unit->id]);

    expect(fn () => app(UnitAvailability::class)->assign(servicio(), $unit))
        ->toThrow(ValidationException::class, "ya está atendiendo el servicio {$first->code}");
});

it('no asigna una unidad fuera de servicio', function () {
    expect(fn () => app(UnitAvailability::class)->assign(servicio(), unidad(status: AmbulanceStatus::OutOfService)))
        ->toThrow(ValidationException::class, 'fuera de servicio');
});

it('la unidad queda libre cuando el servicio termina o se cancela', function () {
    $unit = unidad();
    $first = servicio(['ambulance_id' => $unit->id]);

    $first->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);
    $second = servicio(['ambulance_id' => $unit->id]);
    $second->update(['status' => DispatchStatus::Cancelled, 'cancelled_at' => now()]);

    expect(app(UnitAvailability::class)->isAvailable($unit))->toBeTrue()
        ->and(servicio(['ambulance_id' => $unit->id])->status)->toBe(DispatchStatus::Assigned);
});

it('la base de datos rechaza dos servicios activos aunque el código falle', function () {
    $unit = unidad();
    servicio(['ambulance_id' => $unit->id]);

    // Inserción directa, saltándose el modelo y el servicio: simula un error de programación
    expect(fn () => DB::table('dispatches')->insert([
        'code' => 'SRV-TEST', 'type' => 'emergency', 'priority' => 'high', 'status' => 'in_progress',
        'ambulance_id' => $unit->id, 'pickup_address' => 'x', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Servicios terminados de la misma unidad sí se permiten
    DB::table('dispatches')->insert([
        'code' => 'SRV-OLD', 'type' => 'emergency', 'priority' => 'high', 'status' => 'completed',
        'ambulance_id' => $unit->id, 'pickup_address' => 'x', 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(Dispatch::where('ambulance_id', $unit->id)->count())->toBe(2);
});

it('si dos despachadores asignan a la vez, el segundo recibe un aviso y no un error', function () {
    $unit = unidad();
    $mine = servicio();
    $theirs = servicio();

    // El otro despachador guardó un instante antes, sin pasar por la revisión de esta pantalla
    DB::table('dispatches')->where('id', $theirs->id)->update(['ambulance_id' => $unit->id, 'status' => 'assigned']);

    expect(fn () => app(UnitAvailability::class)->assign($mine, $unit))
        ->toThrow(ValidationException::class);
    expect($mine->fresh()->ambulance_id)->toBeNull();
});

it('el formulario solo ofrece unidades libres y avisa si se elige una ocupada', function () {
    $this->actingAs(User::factory()->create());
    $free = unidad('SAR-01');
    $busy = unidad('SAR-02');
    $broken = unidad('SAR-03', AmbulanceStatus::OutOfService);
    servicio(['ambulance_id' => $busy->id]);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'type' => DispatchType::Emergency,
            'priority' => DispatchPriority::High,
            'status' => DispatchStatus::Pending,
            'pickup_address' => 'La Virgen centro',
            'ambulance_id' => $busy->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['ambulance_id']);

    $options = app(UnitAvailability::class)->assignableUnits()->pluck('code')->all();
    expect($options)->toBe(['SAR-01'])
        ->not->toContain($busy->code)
        ->not->toContain($broken->code);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'type' => DispatchType::Emergency,
            'priority' => DispatchPriority::High,
            'status' => DispatchStatus::Pending,
            'pickup_address' => 'La Virgen centro',
            'ambulance_id' => $free->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Dispatch::where('ambulance_id', $free->id)->first()->status)->toBe(DispatchStatus::Assigned);
});

it('al editar un servicio conserva su propia unidad como opción válida', function () {
    $this->actingAs(User::factory()->create());
    $unit = unidad();
    $dispatch = servicio(['ambulance_id' => $unit->id]);

    expect(app(UnitAvailability::class)->assignableUnits($dispatch)->pluck('id')->all())->toContain($unit->id);

    Livewire::test(EditDispatch::class, ['record' => $dispatch->getRouteKey()])
        ->fillForm(['description' => 'Paciente consciente'])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('el simulador no asigna una unidad que tiene un servicio puesto a mano', function () {
    Http::fake(['router.project-osrm.org/*' => Http::response([], 503)]);
    Cache::flush();

    $unit = unidad();
    $unit->update(['is_simulated' => true]);
    $manual = servicio(['ambulance_id' => $unit->id]);

    // Emergencia automática esperando unidad
    $waiting = servicio();
    $waiting->forceFill(['created_at' => now()->subMinute()])->saveQuietly();

    (new FleetSimulator(autoDispatch: true))->tick();

    expect($waiting->fresh()->ambulance_id)->toBeNull()
        ->and($manual->fresh()->ambulance_id)->toBe($unit->id);
});
