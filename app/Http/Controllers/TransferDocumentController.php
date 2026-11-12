<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Models\Dispatch;
use App\Models\DispatchDocument;
use App\Services\Transfers\TransferDocuments;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos del traslado (disco privado) y la hoja de traslado para
 * imprimir o guardar en PDF desde el navegador.
 */
class TransferDocumentController extends Controller
{
    public function download(DispatchDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk(DispatchDocument::DISK)->exists($document->path), 404);

        return Storage::disk(DispatchDocument::DISK)->response($document->path, $document->original_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function sheet(Dispatch $dispatch, TransferDocuments $documents): View
    {
        Gate::authorize('view', $dispatch);
        Gate::authorize('viewAny', DispatchDocument::class);

        $dispatch->load(['ambulance', 'hospital', 'patient', 'documents.uploader', 'events.user']);

        $alerts = collect(config('operations.intake.questions'))
            ->filter(fn (array $q, string $key) => isset($dispatch->triage[$key]) && (bool) $dispatch->triage[$key] === $q['when'])
            ->map(fn (array $q) => ucfirst($q['reason']))
            ->values()
            ->all();

        return view('transfers.sheet', [
            'dispatch' => $dispatch,
            'alerts' => $alerts,
            'complaint' => $dispatch->chief_complaint ? config("operations.intake.complaints.{$dispatch->chief_complaint}.label") : null,
            'signature' => $documents->signatureDataUri($dispatch),
            'finished' => $dispatch->status === DispatchStatus::Completed,
        ]);
    }
}
