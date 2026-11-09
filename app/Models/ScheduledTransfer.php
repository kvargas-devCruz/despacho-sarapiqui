<?php

namespace App\Models;

use App\Enums\AmbulanceType;
use App\Enums\ScheduledTransferStatus;
use App\Services\TransferSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Traslado programado o cita médica: el paciente debe llegar al centro
 * de salud a una hora; el sistema calcula a qué hora salir a recogerlo
 * y cuánto tiempo queda ocupada la unidad.
 */
class ScheduledTransfer extends Model
{
    protected $fillable = [
        'series_id',
        'patient_name',
        'patient_phone',
        'patient_national_id',
        'reason',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'hospital_id',
        'appointment_at',
        'pickup_at',
        'busy_until',
        'round_trip',
        'ambulance_type',
        'ambulance_id',
        'status',
        'dispatch_id',
        'notes',
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'ambulance_type' => 'transfer',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'appointment_at' => 'datetime',
            'pickup_at' => 'datetime',
            'busy_until' => 'datetime',
            'round_trip' => 'boolean',
            'ambulance_type' => AmbulanceType::class,
            'status' => ScheduledTransferStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Hora de recogida y tiempo ocupado: se calculan solos
        static::saving(function (ScheduledTransfer $transfer) {
            $schedule = app(TransferSchedule::class);
            $routeChanged = $transfer->exists
                && $transfer->isDirty(['appointment_at', 'pickup_latitude', 'pickup_longitude', 'hospital_id'])
                && ! $transfer->isDirty('pickup_at');

            if (! $transfer->pickup_at || $routeChanged) {
                $transfer->pickup_at = $schedule->suggestedPickup($transfer);
            }

            $transfer->busy_until = $schedule->busyUntil($transfer);
        });
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function ambulance(): BelongsTo
    {
        return $this->belongsTo(Ambulance::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }
}
