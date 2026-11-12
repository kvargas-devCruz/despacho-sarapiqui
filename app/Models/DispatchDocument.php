<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Documento de un traslado (orden médica, epicrisis, foto). Disco privado. */
class DispatchDocument extends Model
{
    public const DISK = 'local';

    public const TYPES = [
        'medical_order' => 'Orden médica',
        'clinical_note' => 'Epicrisis o nota clínica',
        'other' => 'Otro documento',
    ];

    protected $fillable = [
        'dispatch_id',
        'type',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
        'uploaded_via',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (DispatchDocument $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Documento';
    }
}
