<?php

namespace App\Models;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Services\DispatchCostCalculator;
use App\Services\UnitAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Dispatch extends Model
{
    protected $fillable = [
        'code',
        'type',
        'priority',
        'status',
        'ambulance_id',
        'hospital_id',
        'patient_name',
        'patient_age',
        'description',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'assigned_at',
        'on_scene_at',
        'completed_at',
        'cancelled_at',
    ];

    /** Igual que en la base de datos, para que el modelo conozca su estado desde que se crea */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $hidden = [
        'tracking_token',
    ];

    protected function casts(): array
    {
        return [
            'type' => DispatchType::class,
            'priority' => DispatchPriority::class,
            'status' => DispatchStatus::class,
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'assigned_at' => 'datetime',
            'on_scene_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'tracking_shared_at' => 'datetime',
            'distance_km' => 'float',
            'distance_estimated' => 'boolean',
            'duration_minutes' => 'integer',
            'cost_fuel' => 'float',
            'cost_crew' => 'float',
            'cost_maintenance' => 'float',
            'cost_supplies' => 'float',
            'cost_total' => 'float',
            'reference_charge' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // Genera un código como SRV-261103-A7K2 al crear el servicio
        static::creating(function (Dispatch $dispatch) {
            $dispatch->code ??= 'SRV-' . now()->format('ymd') . '-' . Str::upper(Str::random(4));
        });

        // Reglas de asignación: el estado sigue a la unidad y una unidad ocupada no se repite
        static::saving(function (Dispatch $dispatch) {
            $dispatch->syncStatusWithUnit();

            if ($dispatch->ambulance_id && $dispatch->isActive() && $dispatch->isDirty(['ambulance_id', 'status'])) {
                $unit = Ambulance::find($dispatch->ambulance_id);

                if ($unit && $reason = app(UnitAvailability::class)->unavailableReason($unit, $dispatch)) {
                    throw ValidationException::withMessages(['ambulance_id' => $reason]);
                }
            }
        });

        // Al completarse, calcula solo su costo de operación
        static::saved(function (Dispatch $dispatch) {
            if ($dispatch->status === DispatchStatus::Completed && $dispatch->cost_total === null) {
                app(DispatchCostCalculator::class)->apply($dispatch);
            }
        });
    }

    /** Pendiente con unidad, asignado o en curso: ocupa a la unidad */
    public function isActive(): bool
    {
        return in_array($this->status, UnitAvailability::ACTIVE_STATUSES, true);
    }

    /**
     * Al poner unidad a un servicio pendiente queda "asignado" con su hora;
     * si se le quita la unidad a uno asignado, vuelve a "pendiente".
     */
    private function syncStatusWithUnit(): void
    {
        if ($this->ambulance_id && $this->status === DispatchStatus::Pending) {
            $this->status = DispatchStatus::Assigned;
        }

        if (! $this->ambulance_id && $this->status === DispatchStatus::Assigned) {
            $this->status = DispatchStatus::Pending;
            $this->assigned_at = null;
        }

        if ($this->ambulance_id && $this->status === DispatchStatus::Assigned && $this->isDirty('ambulance_id') && ! $this->isDirty('assigned_at')) {
            $this->assigned_at = now();
        }
    }

    /**
     * Crea (o reutiliza) el enlace de seguimiento para compartir. Si el enlace
     * anterior ya venció, se crea uno nuevo con su propio plazo.
     */
    public function shareTracking(): string
    {
        if (blank($this->tracking_token) || $this->trackingExpired()) {
            $this->forceFill([
                'tracking_token' => Str::random(40),
                'tracking_shared_at' => now(),
            ])->saveQuietly();
        }

        return $this->trackingUrl();
    }

    public function trackingUrl(): ?string
    {
        return $this->tracking_token ? route('tracking.show', $this->tracking_token) : null;
    }

    /** Anula el enlace: quien lo tenga recibe "no encontrado" */
    public function revokeTracking(): void
    {
        $this->forceFill(['tracking_token' => null, 'tracking_shared_at' => null])->saveQuietly();
    }

    /** Pasó la vida máxima del enlace desde que se compartió */
    public function trackingExpired(): bool
    {
        return $this->tracking_shared_at !== null
            && $this->tracking_shared_at->lte(now()->subHours(config('operations.tracking.max_hours')));
    }

    /** El enlace deja de mostrar la unidad un rato después de terminar el servicio */
    public function trackingIsOpen(): bool
    {
        if ($this->status === DispatchStatus::Cancelled || $this->trackingExpired()) {
            return false;
        }

        if ($this->status === DispatchStatus::Completed) {
            return $this->completed_at?->gt(now()->subHours(config('operations.tracking.hours_after_completion'))) ?? false;
        }

        return true;
    }

    /** Margen estimado frente a la tarifa de referencia */
    public function margin(): ?float
    {
        return $this->cost_total === null || $this->reference_charge === null
            ? null
            : $this->reference_charge - $this->cost_total;
    }

    public function ambulance(): BelongsTo
    {
        return $this->belongsTo(Ambulance::class);
    }

    /** Hospital de destino */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
