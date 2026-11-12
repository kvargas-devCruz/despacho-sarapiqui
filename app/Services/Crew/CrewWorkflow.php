<?php

namespace App\Services\Crew;

use App\Enums\AmbulanceStatus;
use App\Enums\DispatchStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Dispatches\DispatchResource;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\User;
use App\Services\Transfers\HospitalNotifier;
use App\Services\Transfers\TransferDocuments;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pasos del servicio desde el celular de la tripulación:
 * aceptar (o decir que no se puede) → salir → llegar a la escena →
 * (trasladar → llegar al hospital) → terminar.
 *
 * Cada paso cambia el estado de la unidad y del servicio, guarda la hora
 * (sirve para los tiempos de respuesta) y deja una línea en la bitácora.
 */
class CrewWorkflow
{
    public const STEPS = [
        'accept' => ['label' => 'Aceptar el servicio', 'done' => 'Servicio aceptado'],
        'depart' => ['label' => 'Salimos hacia la escena', 'done' => 'En camino a la escena'],
        'arrive' => ['label' => 'Llegamos a la escena', 'done' => 'En la escena'],
        'transport' => ['label' => 'Salimos hacia el hospital', 'done' => 'Trasladando al paciente'],
        'hospital' => ['label' => 'Llegamos al hospital', 'done' => 'En el hospital'],
        'receive' => ['label' => 'Firma de recibido', 'done' => 'Paciente entregado con firma'],
        'finish' => ['label' => 'Terminar el servicio', 'done' => 'Servicio terminado'],
    ];

    /** Siguiente paso que puede tocar la tripulación, o null si no hay servicio */
    public function next(Ambulance $unit, ?Dispatch $dispatch): ?string
    {
        if (! $dispatch || ! in_array($dispatch->status, [DispatchStatus::Assigned, DispatchStatus::InProgress], true)) {
            return null;
        }

        if ($dispatch->status === DispatchStatus::Assigned && ! $dispatch->accepted_at) {
            return 'accept';
        }

        return match ($unit->status) {
            AmbulanceStatus::EnRoute => 'arrive',
            AmbulanceStatus::OnScene => $dispatch->hospital_id ? 'transport' : 'finish',
            AmbulanceStatus::Transporting => 'hospital',
            AmbulanceStatus::AtHospital => $dispatch->received_at ? 'finish' : 'receive',
            default => $dispatch->status === DispatchStatus::InProgress
                ? ($dispatch->hospital_id ? 'transport' : 'finish')
                : 'depart',
        };
    }

    /** Pasos que aplican a este servicio, para la barra de avance */
    public function stepsFor(Dispatch $dispatch): array
    {
        return $dispatch->hospital_id
            ? ['accept', 'depart', 'arrive', 'transport', 'hospital', 'receive', 'finish']
            : ['accept', 'depart', 'arrive', 'finish'];
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

            if (! $dispatch || $step === 'receive' || $this->next($unit, $dispatch) !== $step) {
                throw ValidationException::withMessages([
                    'step' => 'Este paso ya no aplica: el servicio cambió. La pantalla se actualizó.',
                ]);
            }

            $note = 'Tripulación: ' . mb_strtolower(self::STEPS[$step]['done']);

            if ($step === 'accept') {
                $dispatch->forceFill(['accepted_at' => now()])->saveQuietly();
                $unit->forceFill(['is_simulated' => false])->save();   // la tripulación toma el control
                $dispatch->logStatus("Tripulación de {$unit->code}: servicio aceptado");
                $this->notifyDispatch("{$unit->code} aceptó {$dispatch->code}", $dispatch->pickup_address, 'success', $dispatch);

                return $dispatch->fresh(['hospital', 'patient']);
            }

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

            // Sale con el paciente: se avisa al hospital con la hora estimada
            if ($step === 'transport') {
                app(HospitalNotifier::class)->patientOnTheWay($dispatch->fresh(['hospital']), $unit->fresh());
            }

            return $dispatch->fresh(['hospital', 'patient']);
        });
    }

    /** Firma de quien recibe al paciente en el hospital */
    public function receive(Ambulance $unit, int $dispatchId, string $name, string $role, string $signature): Dispatch
    {
        return DB::transaction(function () use ($unit, $dispatchId, $name, $role, $signature) {
            $unit = Ambulance::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $dispatch = Dispatch::whereKey($dispatchId)->where('ambulance_id', $unit->id)->lockForUpdate()->first();

            if (! $dispatch || $this->next($unit, $dispatch) !== 'receive') {
                throw ValidationException::withMessages(['step' => 'La firma se pide al llegar al hospital.']);
            }

            return app(TransferDocuments::class)->receive($dispatch, $name, $role, $signature);
        });
    }

    /**
     * La tripulación no puede ir: el servicio vuelve a pendiente para que el
     * despacho asigne otra unidad, y se avisa en la campana del panel.
     */
    public function reject(Ambulance $unit, int $dispatchId, string $reason): Dispatch
    {
        $config = config("operations.crew_reject_reasons.{$reason}")
            ?? throw ValidationException::withMessages(['reason' => 'Elija un motivo.']);

        return DB::transaction(function () use ($unit, $dispatchId, $config) {
            $unit = Ambulance::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $dispatch = Dispatch::whereKey($dispatchId)->where('ambulance_id', $unit->id)->lockForUpdate()->first();

            if (! $dispatch || $this->next($unit, $dispatch) !== 'accept') {
                throw ValidationException::withMessages([
                    'step' => 'El servicio ya fue aceptado o cambió: avise al despacho por radio.',
                ]);
            }

            $dispatch->update(['ambulance_id' => null]);   // vuelve a pendiente
            $dispatch->logStatus("Tripulación de {$unit->code} no puede atender: {$config['label']}");

            $this->unit($unit, $config['out_of_service'] ? AmbulanceStatus::OutOfService : AmbulanceStatus::Available);

            $this->notifyDispatch(
                "{$unit->code} no puede atender {$dispatch->code}",
                "{$config['label']}. El servicio volvió a pendiente: asigne otra unidad.",
                'danger',
                $dispatch,
            );

            return $dispatch->fresh();
        });
    }

    /** Aviso en la campana para quienes despachan */
    private function notifyDispatch(string $title, string $body, string $status, Dispatch $dispatch): void
    {
        $users = User::whereIn('role', [UserRole::Admin, UserRole::Supervisor, UserRole::Dispatcher])->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->status($status)
            ->icon($status === 'danger' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
            ->actions([
                Action::make('open')->label('Abrir servicio')->url(DispatchResource::getUrl('edit', ['record' => $dispatch])),
            ])
            ->sendToDatabase($users);
    }

    private function unit(Ambulance $unit, AmbulanceStatus $status): void
    {
        // Con la tripulación al mando, el simulador deja de mover esta unidad
        $unit->forceFill(['status' => $status, 'is_simulated' => false])->save();
    }
}
