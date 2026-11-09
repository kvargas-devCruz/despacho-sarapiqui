<?php

namespace App\Services;

use App\Enums\AmbulanceStatus;
use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Enums\ScheduledTransferStatus;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\ScheduledTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Agenda de traslados programados y citas médicas: calcula la hora de
 * recogida, detecta choques de horario y convierte la cita en servicio.
 */
class TransferSchedule
{
    /** Minutos de viaje desde la casa del paciente hasta el centro de salud */
    public function travelMinutes(ScheduledTransfer $transfer): int
    {
        $config = config('operations');
        $hospital = $transfer->hospital ?? $transfer->hospital()->first();

        if (! $hospital || ! $transfer->pickup_latitude) {
            return $config['schedule']['default_travel_minutes'];
        }

        $km = Geo::km($transfer->pickup_latitude, $transfer->pickup_longitude, $hospital->latitude, $hospital->longitude)
            * $config['tracking']['road_factor'];

        return (int) ceil($km / $config['schedule']['transfer_speed_kmh'] * 60);
    }

    /** Hora de salir a recoger: cita − viaje − subir al paciente − margen */
    public function suggestedPickup(ScheduledTransfer $transfer): Carbon
    {
        $config = config('operations.schedule');

        return $transfer->appointment_at->copy()
            ->subMinutes($this->travelMinutes($transfer) + $config['boarding_minutes'] + $config['arrival_buffer_minutes'])
            ->floorMinutes(5);
    }

    /** Hasta cuándo queda ocupada la unidad (con regreso: espera la cita y vuelve) */
    public function busyUntil(ScheduledTransfer $transfer): Carbon
    {
        $config = config('operations.schedule');

        return $transfer->round_trip
            ? $transfer->appointment_at->copy()->addMinutes($config['appointment_minutes'] + $this->travelMinutes($transfer) + $config['boarding_minutes'])
            : $transfer->appointment_at->copy()->addMinutes($config['handoff_minutes']);
    }

    /**
     * Choques de horario y avisos de la cita.
     *
     * @return array<int, string>
     */
    public function conflicts(ScheduledTransfer $transfer): array
    {
        if ($transfer->status && ! $transfer->status->isActive()) {
            return [];
        }

        $start = $transfer->pickup_at ?? $this->suggestedPickup($transfer);
        $end = $transfer->busy_until ?? $this->busyUntil($transfer);
        $warnings = [];

        $overlapping = ScheduledTransfer::query()
            ->whereIn('status', [ScheduledTransferStatus::Scheduled, ScheduledTransferStatus::Confirmed])
            ->when($transfer->exists, fn ($q) => $q->whereKeyNot($transfer->getKey()))
            ->where('pickup_at', '<', $end)
            ->where('busy_until', '>', $start)
            ->get();

        // La misma unidad en dos traslados a la vez
        if ($transfer->ambulance_id) {
            $clash = $overlapping->firstWhere('ambulance_id', $transfer->ambulance_id);
            if ($clash) {
                $warnings[] = "La unidad ya tiene el traslado de {$clash->patient_name} entre {$clash->pickup_at->format('g:i a')} y {$clash->busy_until->format('g:i a')}.";
            }
        }

        // Más traslados a la vez que unidades del tipo requerido
        $type = $transfer->ambulance_type;
        $units = Ambulance::where('type', $type)->where('status', '!=', AmbulanceStatus::OutOfService)->count();
        $sameType = $overlapping->where('ambulance_type', $type)->count() + 1;
        if ($sameType > $units) {
            $warnings[] = "A esa hora hay {$sameType} traslados que necesitan {$type->getLabel()} y solo hay {$units} " . ($units === 1 ? 'unidad disponible' : 'unidades disponibles') . '.';
        }

        // El mismo paciente dos veces el mismo día
        $samePatient = ScheduledTransfer::query()
            ->whereIn('status', [ScheduledTransferStatus::Scheduled, ScheduledTransferStatus::Confirmed])
            ->when($transfer->exists, fn ($q) => $q->whereKeyNot($transfer->getKey()))
            ->whereDate('appointment_at', $transfer->appointment_at->toDateString())
            ->where(fn ($q) => $q->where('patient_name', $transfer->patient_name)
                ->when($transfer->patient_national_id, fn ($q) => $q->orWhere('patient_national_id', $transfer->patient_national_id)))
            ->exists();
        if ($samePatient) {
            $warnings[] = 'El paciente ya tiene otro traslado ese mismo día.';
        }

        if ($start->isPast()) {
            $warnings[] = 'La hora de recogida ya pasó.';
        }

        return $warnings;
    }

    /**
     * Crea una cita por cada día elegido de la semana hasta una fecha (por
     * ejemplo, hemodiálisis lunes, miércoles y viernes).
     *
     * @param  array<int, int>  $weekdays  1 = lunes … 7 = domingo
     * @return Collection<int, ScheduledTransfer>
     */
    public function createSeries(array $attributes, array $weekdays, Carbon $until): Collection
    {
        $first = Carbon::parse($attributes['appointment_at']);
        $series = (string) Str::uuid();
        $created = collect();

        return DB::transaction(function () use ($attributes, $weekdays, $until, $first, $series, $created) {
            for ($day = $first->copy(); $day->lte($until->copy()->endOfDay()) && $created->count() < 120; $day->addDay()) {
                if (! in_array($day->dayOfWeekIso, $weekdays, true) && ! $day->equalTo($first)) {
                    continue;
                }

                $created->push(ScheduledTransfer::create([
                    ...$attributes,
                    'series_id' => $series,
                    'appointment_at' => $day->copy(),
                    'pickup_at' => null,
                ]));
            }

            return $created;
        });
    }

    /** Convierte la cita en un servicio del despacho (sale en el mapa) */
    public function dispatch(ScheduledTransfer $transfer): Dispatch
    {
        if (! $transfer->status->isActive()) {
            throw ValidationException::withMessages(['transfer' => 'Este traslado ya no está activo.']);
        }

        $hospital = $transfer->hospital;

        return DB::transaction(function () use ($transfer, $hospital) {
            $dispatch = Dispatch::create([
                'type' => DispatchType::Transfer,
                'priority' => DispatchPriority::Low,
                'status' => $transfer->ambulance_id ? DispatchStatus::Assigned : DispatchStatus::Pending,
                'ambulance_id' => $transfer->ambulance_id,
                'hospital_id' => $hospital->id,
                'patient_name' => $transfer->patient_name,
                'description' => trim(($transfer->reason ?? 'Traslado programado') . ". Cita a las {$transfer->appointment_at->format('g:i a')}"
                    . ($transfer->round_trip ? ', con regreso.' : '.')),
                'pickup_address' => $transfer->pickup_address,
                'pickup_latitude' => $transfer->pickup_latitude,
                'pickup_longitude' => $transfer->pickup_longitude,
                'assigned_at' => $transfer->ambulance_id ? now() : null,
            ]);

            $transfer->update(['status' => ScheduledTransferStatus::Dispatched, 'dispatch_id' => $dispatch->id]);

            return $dispatch;
        });
    }
}
