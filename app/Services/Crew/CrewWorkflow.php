<?php

namespace App\Services\Crew;

use App\Enums\AmbulanceStatus;
use App\Enums\DispatchStatus;
use App\Models\Ambulance;
use App\Models\Dispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pasos del servicio desde el celular de la tripulación:
 * salir → llegar a la escena → (trasladar → llegar al hospital) → terminar.
 *
 * Cada paso cambia el estado de la unidad y del servicio, guarda la hora
 * (sirve para los tiempos de respuesta) y deja una línea en la bitácora.
 */
class CrewWorkflow
{
    public const STEPS = [
        'depart' => ['label' => 'Salimos hacia la escena', 'done' => 'En camino a la escena'],
        'arrive' => ['label' => 'Llegamos a la escena', 'done' => 'En la escena'],
        'transport' => ['label' => 'Salimos hacia el hospital', 'done' => 'Trasladando al paciente'],
        'hospital' => ['label' => 'Llegamos al hospital', 'done' => 'En el hospital'],
        'finish' => ['label' => 'Terminar el servicio', 'done' => 'Servicio terminado'],
    ];

    /** Siguiente paso que puede tocar la tripulación, o null si no hay servicio */
    public function next(Ambulance $unit, ?Dispatch $dispatch): ?string
    {
        if (! $dispatch || ! in_array($dispatch->status, [DispatchStatus::Assigned, DispatchStatus::InProgress], true)) {
            return null;
        }

        return match ($unit->status) {
            AmbulanceStatus::EnRoute => 'arrive',
            AmbulanceStatus::OnScene => $dispatch->hospital_id ? 'transport' : 'finish',
            AmbulanceStatus::Transporting => 'hospital',
            AmbulanceStatus::AtHospital => 'finish',
            default => $dispatch->status === DispatchStatus::InProgress
                ? ($dispatch->hospital_id ? 'transport' : 'finish')
                : 'depart',
        };
    }

    /** Pasos que aplican a este servicio, para la barra de avance */
    public function stepsFor(Dispatch $dispatch): array
    {
        return $dispatch->hospital_id
            ? ['depart', 'arrive', 'transport', 'hospital', 'finish']
            : ['depart', 'arrive', 'finish'];
    }

    /**
     * Aplica el paso. Si no es el que toca (doble toque, otro celular ya lo
     * marcó o el despacho cambió el servicio), no hace nada y avisa.
     */
    public function advance(Ambulance $unit, int $dispatchId, string $step): Dispatch
    {
        return DB::transaction(function () use ($unit, $dispatchId, $step) {
            $unit = Ambulance::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $dispatch = Dispatch::whereKey($dispatchId)->where('ambulance_id', $unit->id)->lockForUpdate()->first();

            if (! $dispatch || $this->next($unit, $dispatch) !== $step) {
                throw ValidationException::withMessages([
                    'step' => 'Este paso ya no aplica: el servicio cambió. La pantalla se actualizó.',
                ]);
            }

            $note = 'Tripulación: ' . mb_strtolower(self::STEPS[$step]['done']);

            match ($step) {
                'depart' => $this->unit($unit, AmbulanceStatus::EnRoute),
                'arrive' => $this->unit($unit, AmbulanceStatus::OnScene),
                'transport' => $this->unit($unit, AmbulanceStatus::Transporting),
                'hospital' => $this->unit($unit, AmbulanceStatus::AtHospital),
                'finish' => $this->unit($unit, AmbulanceStatus::Available),
            };

            match ($step) {
                // La bitácora del servicio la escribe el propio modelo al cambiar de estado
                'arrive' => $dispatch->update(['status' => DispatchStatus::InProgress, 'on_scene_at' => now()]),
                'finish' => $dispatch->update(['status' => DispatchStatus::Completed, 'completed_at' => now()]),
                default => $dispatch->logStatus($note),
            };

            if (in_array($step, ['arrive', 'finish'], true)) {
                $dispatch->events()->reorder()->latest('id')->first()?->update(['note' => $note]);
            }

            return $dispatch->fresh(['hospital', 'patient']);
        });
    }

    private function unit(Ambulance $unit, AmbulanceStatus $status): void
    {
        // Con la tripulación al mando, el simulador deja de mover esta unidad
        $unit->forceFill(['status' => $status, 'is_simulated' => false])->save();
    }
}
