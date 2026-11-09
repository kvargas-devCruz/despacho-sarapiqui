<?php

namespace App\Services;

use App\Enums\AmbulanceStatus;
use App\Enums\DispatchStatus;
use App\Models\Ambulance;
use App\Models\Dispatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Única puerta para saber si una unidad está libre y para asignarla.
 *
 * La usan el formulario de servicios, la agenda y el simulador. Una unidad
 * está libre si no está fuera de servicio y no tiene otro servicio activo
 * (pendiente con unidad, asignado o en curso).
 *
 * La regla se cuida en tres capas:
 * 1. El formulario solo ofrece unidades libres.
 * 2. Al asignar, se bloquea la fila de la unidad y se vuelve a revisar.
 * 3. La base de datos tiene un índice único que rechaza dos servicios activos.
 */
class UnitAvailability
{
    /** Estados en los que el servicio ocupa a la unidad */
    public const ACTIVE_STATUSES = [
        DispatchStatus::Pending,
        DispatchStatus::Assigned,
        DispatchStatus::InProgress,
    ];

    /** Servicio activo que ocupa a la unidad (sin contar el que se está editando) */
    public function activeDispatchFor(Ambulance|int $ambulance, ?Dispatch $except = null): ?Dispatch
    {
        return Dispatch::query()
            ->where('ambulance_id', $ambulance instanceof Ambulance ? $ambulance->getKey() : $ambulance)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($except?->exists, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->first();
    }

    /** Motivo por el que la unidad no se puede asignar, o null si está libre */
    public function unavailableReason(Ambulance $ambulance, ?Dispatch $except = null): ?string
    {
        if ($ambulance->status === AmbulanceStatus::OutOfService) {
            return "La unidad {$ambulance->code} está fuera de servicio.";
        }

        $busy = $this->activeDispatchFor($ambulance, $except);

        return $busy
            ? "La unidad {$ambulance->code} ya está atendiendo el servicio {$busy->code}."
            : null;
    }

    public function isAvailable(Ambulance $ambulance, ?Dispatch $except = null): bool
    {
        return $this->unavailableReason($ambulance, $except) === null;
    }

    /**
     * Unidades que se pueden asignar a un servicio (incluye la que ya tiene).
     *
     * @return Builder<Ambulance>
     */
    public function assignableUnits(?Dispatch $for = null): Builder
    {
        return Ambulance::query()
            ->where('status', '!=', AmbulanceStatus::OutOfService)
            ->whereDoesntHave('dispatches', fn (Builder $query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->when($for?->exists, fn (Builder $query) => $query->whereKeyNot($for->getKey())));
    }

    /**
     * Asigna la unidad al servicio de forma segura.
     *
     * Bloquea la fila de la unidad mientras revisa y guarda: si dos
     * despachadores asignan la misma unidad al mismo tiempo, el segundo espera,
     * ve que ya está ocupada y recibe el aviso.
     *
     * @throws ValidationException
     */
    public function assign(Dispatch $dispatch, Ambulance|int $ambulance): Dispatch
    {
        $ambulanceId = $ambulance instanceof Ambulance ? $ambulance->getKey() : $ambulance;

        try {
            return DB::transaction(function () use ($dispatch, $ambulanceId) {
                $unit = Ambulance::query()->lockForUpdate()->findOrFail($ambulanceId);

                if ($reason = $this->unavailableReason($unit, $dispatch)) {
                    throw ValidationException::withMessages(['ambulance_id' => $reason]);
                }

                $dispatch->ambulance_id = $unit->getKey();
                $dispatch->save();

                return $dispatch;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'ambulance_id' => 'La unidad se acaba de asignar a otro servicio. Elija otra.',
            ]);
        }
    }
}
