<?php

namespace App\Models;

use App\Models\Concerns\HasProtectedNationalId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paciente ya atendido. Sirve para reconocerlo cuando vuelve a llamar y
 * llenar sus datos solos (dirección, ubicación y notas de cuidado).
 */
class Patient extends Model
{
    use HasProtectedNationalId;

    protected $fillable = [
        'full_name',
        'national_id',
        'phone',
        'address',
        'latitude',
        'longitude',
        'care_notes',
    ];

    protected $hidden = [
        'national_id',
        'national_id_hash',
    ];

    protected function casts(): array
    {
        return [
            // Cifrada en la base de datos (Ley 8968)
            'national_id' => 'encrypted',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Patient $patient) {
            $patient->phone = self::normalizePhone($patient->phone);
        });
    }

    /** Solo dígitos y los últimos 8 (número de Costa Rica sin el 506) */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : substr($digits, -8);
    }

    /**
     * Paciente que coincide con la cédula o, si no hay cédula, con el teléfono.
     */
    public static function recognize(?string $phone, ?string $nationalId): ?self
    {
        if (filled($nationalId) && $patient = self::query()->whereNationalId($nationalId)->first()) {
            return $patient;
        }

        $phone = self::normalizePhone($phone);

        return $phone ? self::query()->where('phone', $phone)->latest('updated_at')->first() : null;
    }

    /**
     * Guarda o actualiza al paciente a partir de un servicio recién creado, para
     * reconocerlo la próxima vez. Sin nombre y sin cédula o teléfono no se guarda.
     */
    public static function rememberFrom(Dispatch $dispatch, ?string $nationalId = null): ?self
    {
        $patient = $dispatch->patient ?? self::recognize($dispatch->caller_phone, $nationalId);

        if (! $patient) {
            if (blank($dispatch->patient_name) || (blank($nationalId) && blank($dispatch->caller_phone))) {
                return null;
            }

            $patient = new self(['full_name' => $dispatch->patient_name]);
        }

        $patient->fill(array_filter([
            'national_id' => filled($nationalId) ? $nationalId : null,
            'phone' => $patient->phone ?: $dispatch->caller_phone,
            'address' => $dispatch->pickup_address,
            'latitude' => $dispatch->pickup_latitude,
            'longitude' => $dispatch->pickup_longitude,
        ], fn ($value) => filled($value)))->save();

        if ($dispatch->patient_id !== $patient->id) {
            $dispatch->forceFill(['patient_id' => $patient->id])->saveQuietly();
        }

        return $patient;
    }

    /** Resumen para el despachador: cuántas veces se ha atendido y cuándo fue la última */
    public function summary(): string
    {
        $count = $this->dispatches()->count();
        $last = $this->dispatches()->latest()->first();

        return trim(($count > 0 ? "Paciente frecuente: {$count} " . ($count === 1 ? 'servicio' : 'servicios') . ', el último ' . $last->created_at->diffForHumans() . '.' : 'Paciente registrado.')
            . (filled($this->care_notes) ? " Cuidados: {$this->care_notes}" : ''));
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeFrequent(Builder $query, int $minimum = 2): void
    {
        $query->has('dispatches', '>=', $minimum);
    }
}
