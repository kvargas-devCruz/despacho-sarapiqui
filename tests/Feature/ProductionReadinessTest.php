<?php

use App\Enums\UserRole;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\DispatchEvent;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Preparar para producción: revisar qué falta y quitar la demostración.
*/

beforeEach(function () {
    Http::fake(['*' => Http::response([], 503)]);
    config(['app.demo' => false]);
});

function produccionLista(): void
{
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.debug' => false,
        'app.demo' => false,
        'app.url' => 'https://despacho.zurqui.cr',
        'mail.default' => 'smtp',
        'operations.routing.osrm_url' => 'https://rutas.zurqui.cr',
    ]);
}

it('la revisión marca lo que falta mientras hay demo', function () {
    $this->seed(DemoSeeder::class);

    $this->artisan('ops:production-check')
        ->expectsOutputToContain('Sin usuarios de demostración')
        ->expectsOutputToContain('ops:remove-demo')
        ->expectsOutputToContain('El OSRM público es solo para pruebas')
        ->assertFailed();
});

it('quita la demo y conserva hospitales y unidades reales', function () {
    $this->seed(DemoSeeder::class);
    $real = Ambulance::create(['code' => 'ZUR-01', 'plate' => 'AMB-9001', 'type' => 'basic', 'status' => 'available', 'is_simulated' => false]);
    $hospitals = Hospital::count();

    expect(Dispatch::count())->toBeGreaterThan(0);

    $this->artisan('ops:remove-demo')
        ->expectsConfirmation('¿Quitar estos datos? No se puede deshacer.', 'yes')
        ->assertSuccessful();

    expect(User::whereIn('email', config('operations.demo.users'))->exists())->toBeFalse()
        ->and(Ambulance::where('is_simulated', true)->exists())->toBeFalse()
        ->and(Ambulance::find($real->id))->not->toBeNull()
        ->and(Dispatch::count())->toBe(0)
        ->and(DispatchEvent::count())->toBe(0)
        ->and(Patient::count())->toBe(0)
        ->and(Hospital::count())->toBe($hospitals);
});

it('si no se confirma no borra nada', function () {
    $this->seed(DemoSeeder::class);
    $count = Dispatch::count();

    $this->artisan('ops:remove-demo')
        ->expectsConfirmation('¿Quitar estos datos? No se puede deshacer.', 'no')
        ->assertFailed();

    expect(Dispatch::count())->toBe($count);
});

it('no borra nada si ya hay trabajo de usuarios reales', function () {
    $this->seed(DemoSeeder::class);
    $real = User::factory()->create(['role' => UserRole::Dispatcher]);
    $this->actingAs($real);
    Dispatch::create(['type' => 'transfer', 'priority' => 'low', 'pickup_address' => 'Clínica de Puerto Viejo', 'patient_name' => 'Paciente real']);

    $this->artisan('ops:remove-demo', ['--force' => true])
        ->expectsOutputToContain('usuarios reales')
        ->assertFailed();

    expect(Dispatch::where('patient_name', 'Paciente real')->exists())->toBeTrue();
});

it('con todo configurado la revisión pasa', function () {
    produccionLista();
    User::factory()->create(['role' => UserRole::Admin, 'email' => 'jefatura@zurqui.cr', 'password' => 'Clave-segura-2026']);

    $this->artisan('ops:production-check')
        ->expectsOutputToContain('Todo listo para datos reales.')
        ->assertSuccessful();
});

it('el simulador no corre en producción', function () {
    produccionLista();

    $this->artisan('fleet:simulate')
        ->expectsOutputToContain('apagado en producción')
        ->assertFailed();
});
