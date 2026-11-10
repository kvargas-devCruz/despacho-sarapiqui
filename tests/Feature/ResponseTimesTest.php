<?php

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Filament\Resources\Dispatches\Pages\ViewDispatch;
use App\Filament\Resources\Dispatches\RelationManagers\EventsRelationManager;
use App\Filament\Widgets\ResponseTimesWidget;
use App\Models\Dispatch;
use App\Models\User;
use App\Services\ResponseTimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Bitácora de cada servicio y métricas de tiempos de respuesta.
*/

beforeEach(fn () => config(['app.demo' => false]));

/** Servicio con tiempos conocidos: asignado, llegada y cierre en minutos */
function servicioConTiempos(DispatchPriority $priority, int $assign, int $arrive, int $close): Dispatch
{
    $created = now()->subHours(2);

    $dispatch = Dispatch::create(['type' => DispatchType::Emergency, 'priority' => $priority, 'pickup_address' => 'La Virgen']);
    $dispatch->forceFill([
        'created_at' => $created,
        'assigned_at' => $created->copy()->addMinutes($assign),
        'on_scene_at' => $created->copy()->addMinutes($arrive),
        'completed_at' => $created->copy()->addMinutes($close),
        'status' => DispatchStatus::Completed,
    ])->saveQuietly();

    return $dispatch;
}

it('registra en la bitácora cada cambio de estado con hora y usuario', function () {
    $user = User::factory()->create(['name' => 'Despachador Turno A']);
    $this->actingAs($user);

    $dispatch = Dispatch::create(['type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'La Virgen']);
    $dispatch->update(['status' => DispatchStatus::InProgress]);
    $dispatch->update(['status' => DispatchStatus::Completed]);
    $dispatch->update(['description' => 'Solo cambia la descripción']);

    $events = $dispatch->events()->get();

    expect($events->pluck('status')->all())->toBe([DispatchStatus::Pending, DispatchStatus::InProgress, DispatchStatus::Completed])
        ->and($events->first()->user_id)->toBe($user->id);
});

it('guarda la hora de llegada y de cierre aunque el estado se cambie a mano', function () {
    $dispatch = Dispatch::create(['type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'La Virgen']);

    $dispatch->update(['status' => DispatchStatus::InProgress]);
    expect($dispatch->fresh()->on_scene_at)->not->toBeNull();

    $dispatch->update(['status' => DispatchStatus::Completed]);
    expect($dispatch->fresh()->completed_at)->not->toBeNull();
});

it('muestra la bitácora en la ficha del servicio', function () {
    $this->actingAs(User::factory()->create());
    $dispatch = Dispatch::create(['type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'La Virgen']);
    $dispatch->update(['status' => DispatchStatus::Cancelled]);

    Livewire::test(EventsRelationManager::class, ['ownerRecord' => $dispatch, 'pageClass' => ViewDispatch::class])
        ->assertCanSeeTableRecords($dispatch->events);
});

it('calcula la mediana de tiempos y el porcentaje dentro de la meta', function () {
    // Meta de alta prioridad: 15 min
    servicioConTiempos(DispatchPriority::High, assign: 2, arrive: 10, close: 50);
    servicioConTiempos(DispatchPriority::High, assign: 4, arrive: 14, close: 60);
    servicioConTiempos(DispatchPriority::High, assign: 6, arrive: 30, close: 90);   // fuera de meta
    servicioConTiempos(DispatchPriority::Low, assign: 20, arrive: 60, close: 120);   // meta 90 min

    $summary = app(ResponseTimes::class)->summary(now()->subDay());

    expect($summary['services'])->toBe(4)
        ->and($summary['to_assign'])->toBe(5.0)
        ->and($summary['by_priority']['high']['to_arrive'])->toBe(14.0)
        ->and($summary['by_priority']['high']['on_target'])->toBe(67.0)
        ->and($summary['by_priority']['low']['on_target'])->toBe(100.0)
        ->and($summary['on_target'])->toBe(75.0);
});

it('no cuenta servicios cancelados ni falla sin datos', function () {
    Dispatch::create(['type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'x'])
        ->update(['status' => DispatchStatus::Cancelled]);

    $summary = app(ResponseTimes::class)->summary(now()->subDay());

    expect($summary['services'])->toBe(0)
        ->and($summary['to_arrive'])->toBeNull()
        ->and($summary['on_target'])->toBeNull();
});

it('el escritorio muestra los tiempos de respuesta', function () {
    servicioConTiempos(DispatchPriority::High, 3, 12, 40);
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertOk();
    Livewire::test(ResponseTimesWidget::class)
        ->assertSee('Tiempos de respuesta')
        ->assertSee('Llegadas dentro de la meta')
        ->assertSee('100 %');
});
