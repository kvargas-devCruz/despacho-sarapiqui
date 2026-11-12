<?php

namespace App\Services\Transfers;

use App\Models\Dispatch;
use App\Models\DispatchDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Documentos del traslado: orden médica, nota clínica y la firma de quien
 * recibe al paciente en el hospital. Todo va al disco privado.
 */
class TransferDocuments
{
    public const MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    public const MAX_KB = 10240;

    public function store(Dispatch $dispatch, UploadedFile $file, string $type, string $via = 'panel'): DispatchDocument
    {
        if (! array_key_exists($type, DispatchDocument::TYPES)) {
            throw ValidationException::withMessages(['type' => 'Tipo de documento no válido.']);
        }

        $path = $file->storeAs(
            "transfer-documents/{$dispatch->id}",
            Str::uuid() . '.' . ($file->guessExtension() ?: 'bin'),
            DispatchDocument::DISK,
        );

        $document = $dispatch->documents()->create([
            'type' => $type,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName() ?: 'documento', 200, ''),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
            'uploaded_via' => $via,
        ]);

        $dispatch->logStatus(($via === 'crew' ? 'Tripulación' : 'Despacho') . ' adjuntó: ' . mb_strtolower($document->typeLabel()));

        return $document;
    }

    /**
     * Firma de recibido en el hospital. La firma llega como imagen PNG en
     * base64 desde el celular (dibujada con el dedo).
     */
    public function receive(Dispatch $dispatch, string $name, string $role, string $signature): Dispatch
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signature, $match)) {
            throw ValidationException::withMessages(['signature' => 'Falta la firma.']);
        }

        $png = base64_decode($match[1], true);

        // Una firma vacía pesa muy poco; una real tiene trazos
        if ($png === false || strlen($png) < 1500 || ! str_starts_with($png, "\x89PNG")) {
            throw ValidationException::withMessages(['signature' => 'La firma está vacía. Pida que firme con el dedo.']);
        }

        $path = "transfer-documents/{$dispatch->id}/recibido-" . Str::uuid() . '.png';
        Storage::disk(DispatchDocument::DISK)->put($path, $png);

        $dispatch->forceFill([
            'received_by_name' => $name,
            'received_by_role' => $role,
            'received_signature_path' => $path,
            'received_at' => now(),
        ])->saveQuietly();

        $dispatch->logStatus("Paciente recibido en el hospital por {$name} ({$role})");

        return $dispatch;
    }

    /** Firma como data URI para la hoja de traslado impresa */
    public function signatureDataUri(Dispatch $dispatch): ?string
    {
        $path = $dispatch->received_signature_path;

        if (! $path || ! Storage::disk(DispatchDocument::DISK)->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk(DispatchDocument::DISK)->get($path));
    }
}
