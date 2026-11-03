<?php

namespace App\Models;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Enums\DispatchType;
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
        ];
    }

    /** Genera un código como SRV-261103-A7K2 al crear el servicio */
    protected static function booted(): void
    {
        static::creating(function (Dispatch $dispatch) {
            $dispatch->code ??= 'SRV-' . now()->format('ymd') . '-' . Str::upper(Str::random(4));
        });
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