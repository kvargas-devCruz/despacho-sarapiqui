<?php

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Filament\Resources\Dispatches\Pages\CreateDispatch;
use App\Models\Dispatch;
use App\Models\Patient;
use App\Models\User;
use App\Services\DuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Paciente frecuente (se reconoce por teléfono o cédula) y aviso de
| solicitudes duplicadas.
*/

beforeEach(fn () => config(['app.demo' => false]));

function llamada(array $attributes = []): Dispatch
{
    return Dispatch::create(array_merge([
        'type' => DispatchType::Emergency,
        'priority' => DispatchPriority::High,
        'caller_phone' => '8888-1234',
        'patient_name' => 'Ana Mora Solís',
        'pickup_address' => 'Chilamate, 200 m norte de la plaza',
        'pickup_latitude' => 10.4470,
        'pickup_longitude' => -84.0780,
    ], $attributes));
}

it('guarda al paciente con la cédula cifrada y lo reconoce por teléfono o cédula', function () {
    $patient = Patient::rememberFrom(llamada(), '2-0567-0890');
    $row = DB::table('patients')->first();

    expect($row->national_id)->not->toContain('0567')
        ->and($row->phone)->toBe('88881234')
        ->and(Patient::recognize('+506 8888 1234', null)?->is($patient))->toBeTrue()
        ->and(Patient::recognize(null, '205670890')?->is($patient))->toBeTrue()
        ->and(Patient::recognize('7000-0000', null))->toBeNull();
});

it('una segunda llamada del mismo paciente queda ligada y suma al historial', function () {
    $patient = Patient::rememberFrom(llamada(), '2-0567-0890');
    $patient->update(['care_notes' => 'Usa oxígeno']);
    llamada()->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);

    $second = llamada(['caller_phone' => '88881234', 'patient_name' => 'Ana Mora']);
    Patient::rememberFrom($second);

    expect($second->fresh()->patient_id)->toBe($patient->id)
        ->and(Patient::count())->toBe(1)
        ->and($patient->fresh()->summary())->toContain('Paciente frecuente')->toContain('Usa oxígeno');
});

it('no guarda pacientes sin nombre o sin forma de reconocerlos', function () {
    expect(Patient::rememberFrom(llamada(['caller_phone' => null])))->toBeNull()
        ->and(Patient::rememberFrom(llamada(['patient_name' => null, 'caller_phone' => '8000-0001'])))->toBeNull()
        ->and(Patient::count())->toBe(0);
});

it('el formulario llena los datos del paciente frecuente al escribir el teléfono', function () {
    Patient::rememberFrom(llamada(), '2-0567-0890');
    Dispatch::query()->update(['status' => 'completed', 'completed_at' => now()]);
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateDispatch::class)
        ->fillForm(['caller_phone' => '8888 1234'])
        ->assertSchemaStateSet([
            'patient_name' => 'Ana Mora Solís',
            'pickup_address' => 'Chilamate, 200 m norte de la plaza',
        ])
        ->assertSee('Paciente frecuente');
});

it('al crear el servicio recuerda al paciente nuevo', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'type' => DispatchType::Emergency,
            'priority' => DispatchPriority::High,
            'status' => DispatchStatus::Pending,
            'caller_phone' => '8777-0000',
            'patient_national_id' => '1-1111-2222',
            'patient_name' => 'Carlos Brenes',
            'pickup_address' => 'Puerto Viejo',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Patient::whereNationalId('111112222')->first()?->full_name)->toBe('Carlos Brenes')
        ->and(Dispatch::first()->patient_id)->not->toBeNull();
});

it('avisa de duplicados por teléfono, paciente o cercanía', function () {
    $first = llamada();
    $detector = app(DuplicateDetector::class);

    expect($detector->find(['caller_phone' => '88881234'])->first()['reason'])->toBe('mismo teléfono')
        ->and($detector->find(['pickup_latitude' => 10.4475, 'pickup_longitude' => -84.0782])->first()['reason'])->toContain('a menos de')
        ->and($detector->find(['pickup_latitude' => 10.5000, 'pickup_longitude' => -84.0000]))->toBeEmpty()
        ->and($detector->warning(['caller_phone' => '8888-1234']))->toContain($first->code)
        ->and($detector->find(['caller_phone' => '88881234'], except: $first))->toBeEmpty();
});

it('no avisa por servicios cerrados ni por los de hace más de una hora', function () {
    llamada()->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]);
    $old = llamada();
    $old->forceFill(['created_at' => now()->subMinutes(config('operations.duplicates.minutes') + 5)])->saveQuietly();

    expect(app(DuplicateDetector::class)->find(['caller_phone' => '88881234']))->toBeEmpty();
});

it('el formulario muestra el aviso de duplicado', function () {
    $first = llamada();
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateDispatch::class)
        ->fillForm(['caller_phone' => '8888-1234'])
        ->assertSee('Posible solicitud duplicada')
        ->assertSee($first->code);
});
