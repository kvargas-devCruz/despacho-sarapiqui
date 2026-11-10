<?php

namespace App\Models\Concerns;

use App\Support\NationalId;
use Illuminate\Database\Eloquent\Builder;

/**
 * Para modelos con cédula cifrada: mantiene al día la huella national_id_hash
 * y permite buscar por cédula exacta con ->whereNationalId('1-1452-0871').
 */
trait HasProtectedNationalId
{
    public static function bootHasProtectedNationalId(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('national_id')) {
                $model->national_id_hash = NationalId::hash($model->national_id);
            }
        });
    }

    /** @param  Builder<static>  $query */
    public function scopeWhereNationalId(Builder $query, ?string $nationalId): void
    {
        $query->where('national_id_hash', NationalId::hash($nationalId));
    }
}
