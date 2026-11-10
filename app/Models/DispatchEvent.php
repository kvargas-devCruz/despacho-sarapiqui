<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la bitácora del servicio: a qué estado pasó, cuándo y quién.
 * Se escribe sola (ver Dispatch::booted) y no se edita.
 */
class DispatchEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'dispatch_id',
        'status',
        'user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => DispatchStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
