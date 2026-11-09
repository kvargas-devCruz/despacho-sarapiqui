<?php

namespace App\Filament\Resources\Dispatches;

use App\Enums\DispatchStatus;
use App\Filament\Resources\Dispatches\Pages\CreateDispatch;
use App\Filament\Resources\Dispatches\Pages\EditDispatch;
use App\Filament\Resources\Dispatches\Pages\ListDispatches;
use App\Filament\Resources\Dispatches\Pages\ViewDispatch;
use App\Filament\Resources\Dispatches\Schemas\DispatchForm;
use App\Filament\Resources\Dispatches\Tables\DispatchesTable;
use App\Models\Dispatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DispatchResource extends Resource
{
    protected static ?string $model = Dispatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'servicios';

    protected static ?string $recordTitleAttribute = 'code';

    /** Muestra en el menú cuántos servicios están pendientes de asignar */
    public static function getNavigationBadge(): ?string
    {
        $pending = Dispatch::where('status', DispatchStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Servicios pendientes de asignar';
    }

    public static function form(Schema $schema): Schema
    {
        return DispatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DispatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispatches::route('/'),
            'create' => CreateDispatch::route('/create'),
            'view' => ViewDispatch::route('/{record}'),
            'edit' => EditDispatch::route('/{record}/edit'),
        ];
    }
}
