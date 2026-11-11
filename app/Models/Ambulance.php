<?php

namespace App\Models;

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Services\UnitAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Ambulance extends Model
{
    protected $fillable = [
        'code',
        'plate',
        'type',
        'status',
        'user_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'last_location_at',
        'is_simulated',
        'gps_accuracy',
    ];

    /** El enlace de la tripulación es una llave: nunca viaja en JSON ni al mapa */
    protected $hidden = ['crew_token'];

    protected function casts(): array
    {
        return [
            'type' => AmbulanceType::class,
            'status' => AmbulanceStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'speed' => 'float',
            'heading' => 'float',
            'last_location_at' => 'datetime',
            'is_simulated' => 'boolean',
            'gps_accuracy' => 'float',
            'crew_token_created_at' => 'datetime',
        ];
    }

    /** Enlace privado de la app de tripulación (se crea la primera vez) */
    public function crewUrl(): string
    {
        if (blank($this->crew_token)) {
            $this->rotateCrewToken();
        }

        return route('crew.show', $this->crew_token);
    }

    /** Enlace nuevo: el anterior deja de servir (celular perdido o cambio de tripulación) */
    public function rotateCrewToken(): void
    {
        $this->forceFill([
            'crew_token' => Str::random(48),
            'crew_token_created_at' => now(),
        ])->saveQuietly();
    }

    /** ¿El último GPS vino de un celular hace menos de 2 minutos? */
    public function hasLiveGps(): bool
    {
        return ! $this->is_simulated && $this->last_location_at?->gt(now()->subMinutes(2));
    }

    /** Paramédico a cargo de la unidad */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    /** Servicio que la unidad está atendiendo en este momento */
    public function activeDispatch(): HasOne
    {
        return $this->hasOne(Dispatch::class)
            ->whereIn('status', UnitAvailability::ACTIVE_STATUSES)
            ->latest('id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(AmbulanceLocation::class);
    }
}
