<?php

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchPriority;
use App\Enums\DispatchType;
use App\Enums\UserRole;
use App\Filament\Resources\Dispatches\Pages\EditDispatch;
use App\Filament\Resources\Dispatches\Pages\ListDispatches;
use App\Filament\Resources\ScheduledTransfers\Pages\ManageScheduledTransfers;
use App\Filament\Widgets\OperatingCostsWidget;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Hospital;
use App\Models\ScheduledTransfer;
use App\Models\User;
use App\Policies\RolePolicy;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Roles del despacho: config/permissions.php aplicado a pantallas, botones y URLs.
*/

beforeEach(fn () => config(['app.demo' => false]));

function comoRol(UserRole $role): User
{
    return User::factory()->role($role)->create();
}

function datosDeDespacho(): array
{
    $hospital = Hospital::create(['name' => 'Hospital San Vicente de Paúl', 'short_name' => 'HSVP', 'latitude' => 10.0, 'longitude' => -84.1]);
    $unit = Ambulance::create(['code' => 'SAR-01', 'plate' => 'AMB-01', 'type' => AmbulanceType::Basic, 'status' => AmbulanceStatus::Available]);
    $dispatch = Dispatch::create(['type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'La Virgen']);

    return compact('hospital', 'unit', 'dispatch');
}

it('cada modelo tiene su policy y su área existe en la matriz', function () {
    foreach (glob(app_path('Models/*.php')) as $file) {
        $model = 'App\\Models\\' . basename($file, '.php');
        $policy = Gate::getPolicyFor($model);

        expect($policy)->toBeInstanceOf(RolePolicy::class, "{$model} no tiene policy");

        $area = (new ReflectionProperty($policy, 'area'))->getValue($policy);
        expect(config("permissions.{$area}"))->toBeArray();
    }
});

it('la matriz solo usa roles y acciones que existen', function () {
    $roles = array_map(fn (UserRole $role) => $role->value, UserRole::cases());

    foreach (config('permissions') as $actions) {
        expect(array_keys($actions))->each->toBeIn(['view', 'create', 'update', 'delete']);

        foreach ($actions as $allowed) {
            expect($allowed)->each->toBeIn($roles);
        }
    }
});

it('responde a cada URL directa según el rol', function (string $url, array $allowedRoles) {
    $data = datosDeDespacho();
    $url = str_replace(['{dispatch}', '{unit}', '{hospital}'], [$data['dispatch']->id, $data['unit']->id, $data['hospital']->id], $url);

    foreach (UserRole::cases() as $role) {
        $this->actingAs(comoRol($role));
        $expected = in_array($role, $allowedRoles, true) ? 200 : 403;

        expect($this->get($url)->status())->toBe($expected, "{$role->value} en {$url}");
    }
})->with([
    'servicios' => ['/admin/dispatches', UserRole::cases()],
    'ver servicio' => ['/admin/dispatches/{dispatch}', UserRole::cases()],
    'nuevo servicio' => ['/admin/dispatches/create', [UserRole::Admin, UserRole::Supervisor, UserRole::Dispatcher]],
    'editar servicio' => ['/admin/dispatches/{dispatch}/edit', [UserRole::Admin, UserRole::Supervisor, UserRole::Dispatcher]],
    'mapa en vivo' => ['/admin/live-map', UserRole::cases()],
    'agenda' => ['/admin/scheduled-transfers', UserRole::cases()],
    'flota' => ['/admin/ambulances', UserRole::cases()],
    'nueva unidad' => ['/admin/ambulances/create', [UserRole::Admin, UserRole::Supervisor]],
    'editar unidad' => ['/admin/ambulances/{unit}/edit', [UserRole::Admin, UserRole::Supervisor]],
    'editar hospital' => ['/admin/hospitals/{hospital}/edit', [UserRole::Admin, UserRole::Supervisor]],
    'usuarios' => ['/admin/users', [UserRole::Admin]],
]);

it('solo consulta no ve botones para crear, editar, compartir ni borrar', function () {
    ['dispatch' => $dispatch] = datosDeDespacho();
    $this->actingAs(comoRol(UserRole::Viewer));

    Livewire::test(ListDispatches::class)
        ->assertActionHidden('create')
        ->assertActionHidden(TestAction::make('edit')->table($dispatch))
        ->assertActionHidden(TestAction::make('shareTracking')->table($dispatch))
        ->assertActionVisible(TestAction::make('view')->table($dispatch));
});

it('el despachador comparte el seguimiento pero no borra servicios', function () {
    ['dispatch' => $dispatch] = datosDeDespacho();
    $this->actingAs(comoRol(UserRole::Dispatcher));

    Livewire::test(EditDispatch::class, ['record' => $dispatch->getRouteKey()])
        ->assertActionVisible('shareTracking')
        ->assertActionHidden('delete');
});

it('solo consulta no puede enviar, confirmar ni cancelar traslados de la agenda', function () {
    $hospital = Hospital::create(['name' => 'Área de Salud', 'short_name' => 'AS', 'latitude' => 10.45, 'longitude' => -84.01]);
    $transfer = ScheduledTransfer::create([
        'patient_name' => 'Rosa Quirós', 'pickup_address' => 'Puerto Viejo', 'hospital_id' => $hospital->id,
        'appointment_at' => now()->addDay()->setTime(10, 0), 'ambulance_type' => AmbulanceType::Transfer,
    ]);

    $this->actingAs(comoRol(UserRole::Viewer));
    Livewire::test(ManageScheduledTransfers::class)
        ->assertActionHidden('create')
        ->assertActionHidden(TestAction::make('dispatch')->table($transfer))
        ->assertActionHidden(TestAction::make('confirm')->table($transfer))
        ->assertActionHidden(TestAction::make('cancel')->table($transfer));

    $this->actingAs(comoRol(UserRole::Dispatcher));
    Livewire::test(ManageScheduledTransfers::class)
        ->assertActionVisible(TestAction::make('dispatch')->table($transfer));
});

it('los costos solo los ven administración y supervisión', function () {
    $this->actingAs(comoRol(UserRole::Supervisor));
    expect(OperatingCostsWidget::canView())->toBeTrue();

    $this->actingAs(comoRol(UserRole::Dispatcher));
    expect(OperatingCostsWidget::canView())->toBeFalse();

    $this->actingAs(comoRol(UserRole::Viewer));
    expect(OperatingCostsWidget::canView())->toBeFalse();
});

it('los usuarios nuevos empiezan con el permiso mínimo', function () {
    $user = User::create(['name' => 'Nuevo', 'email' => 'nuevo@despacho.cr', 'password' => 'clave-segura-123']);

    expect($user->fresh()->role)->toBe(UserRole::Viewer);
});

it('promueve un usuario desde la terminal', function () {
    $user = User::create(['name' => 'Nuevo', 'email' => 'jefe@despacho.cr', 'password' => 'clave-segura-123']);

    $this->artisan('user:role', ['email' => 'jefe@despacho.cr', 'role' => 'supervisor'])->assertSuccessful();
    $this->artisan('user:role', ['email' => 'nadie@despacho.cr', 'role' => 'admin'])->assertFailed();

    expect($user->fresh()->role)->toBe(UserRole::Supervisor);
});
