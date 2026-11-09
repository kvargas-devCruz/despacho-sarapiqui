<?php

namespace App\Models;

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Services\UnitAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

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
        ];
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
