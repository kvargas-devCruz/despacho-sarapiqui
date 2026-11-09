<?php

namespace App\Models;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
use App\Services\DispatchCostCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

        // Al completarse, calcula solo su costo de operación
        static::saved(function (Dispatch $dispatch) {
            if ($dispatch->status === DispatchStatus::Completed && $dispatch->cost_total === null) {
                app(DispatchCostCalculator::class)->apply($dispatch);
            }
        });
    }

    /** Crea (o reutiliza) el enlace de seguimiento para compartir */
    public function shareTracking(): string
    {
        if (blank($this->tracking_token)) {
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

    /** El enlace deja de mostrar la unidad un rato después de terminar el servicio */
    public function trackingIsOpen(): bool
    {
        if ($this->status === DispatchStatus::Cancelled) {
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
