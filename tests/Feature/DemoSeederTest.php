<?php

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Models\Ambulance;
use App\Models\Dispatch;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Laravel Cloud vuelve a cargar la demostración en cada publicación, cuando el
| simulador ya movió los servicios. Así falló el despliegue del 10 de noviembre:
| "La unidad SAR-02 ya está atendiendo el servicio SRV-DEMO-004".
*/

it('vuelve a cargar la demostración aunque el simulador haya asignado unidades', function () {
    config(['app.demo' => false]);
    $this->seed(DemoSeeder::class);

    // Lo que hizo el simulador en el servidor: le puso SAR-02 al servicio pendiente
    // y creó una emergencia automática con SAR-03
    $sar02 = Ambulance::where('code', 'SAR-02')->first();
    Dispatch::where('code', 'SRV-DEMO-001')->update(['status' => 'completed', 'completed_at' => now()]);
    Dispatch::where('code', 'SRV-DEMO-004')->first()->update(['ambulance_id' => $sar02->id]);
    Dispatch::where('code', 'SRV-DEMO-002')->update(['status' => 'completed', 'completed_at' => now()]);
    $simulated = Dispatch::create([
        'type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'Emergencia simulada',
        'ambulance_id' => Ambulance::where('code', 'SAR-03')->value('id'),
    ]);

    $this->seed(DemoSeeder::class);

    expect(Dispatch::where('code', 'SRV-DEMO-001')->first()->ambulance_id)->toBe($sar02->id)
        ->and(Dispatch::where('code', 'SRV-DEMO-004')->first()->ambulance_id)->toBeNull()
        ->and($simulated->fresh()->status)->toBe(DispatchStatus::Cancelled);
});
