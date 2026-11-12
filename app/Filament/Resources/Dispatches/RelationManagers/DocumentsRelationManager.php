<?php

namespace App\Filament\Resources\Dispatches\RelationManagers;

use App\Models\DispatchDocument;
use App\Services\Transfers\TransferDocuments;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documentos del traslado: orden médica, epicrisis o fotos. Se suben desde
 * aquí o desde el celular de la tripulación. Disco privado.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', DispatchDocument::class);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Sin documentos')
            ->emptyStateDescription('Adjunte la orden médica o la nota clínica del traslado.')
            ->columns([
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn (string $state) => DispatchDocument::TYPES[$state] ?? $state)->badge(),
                TextColumn::make('original_name')->label('Archivo')->limit(40),
                TextColumn::make('uploaded_via')->label('Desde')->formatStateUsing(fn (string $state) => $state === 'crew' ? 'Ambulancia' : 'Despacho'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y g:i a'),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Adjuntar documento')
                    ->icon('heroicon-m-paper-clip')
                    ->visible(fn () => Gate::allows('create', DispatchDocument::class))
                    ->schema([
                        Select::make('type')->label('Tipo')->options(DispatchDocument::TYPES)->default('medical_order')->required(),
                        FileUpload::make('file')
                            ->label('Archivo (PDF o foto, máximo 10 MB)')
                            ->disk(DispatchDocument::DISK)
                            ->directory('transfer-documents/tmp')
                            ->visibility('private')
                            ->acceptedFileTypes(TransferDocuments::MIMES)
                            ->maxSize(TransferDocuments::MAX_KB)
                            ->storeFileNamesIn('original_name')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $dispatch = $this->getOwnerRecord();
                        $disk = Storage::disk(DispatchDocument::DISK);
                        $final = "transfer-documents/{$dispatch->id}/" . Str::uuid() . '.' . pathinfo($data['file'], PATHINFO_EXTENSION);
                        $disk->move($data['file'], $final);

                        $dispatch->documents()->create([
                            'type' => $data['type'],
                            'path' => $final,
                            'original_name' => $data['original_name'] ?? basename($final),
                            'mime' => $disk->mimeType($final) ?: 'application/octet-stream',
                            'size' => $disk->size($final),
                            'uploaded_by' => auth()->id(),
                            'uploaded_via' => 'panel',
                        ]);

                        $dispatch->logStatus('Despacho adjuntó: ' . mb_strtolower(DispatchDocument::TYPES[$data['type']]));
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (DispatchDocument $record) => route('transfer-documents.download', $record), shouldOpenInNewTab: true),
                DeleteAction::make()->visible(fn (DispatchDocument $record) => Gate::allows('delete', $record)),
            ]);
    }
}
