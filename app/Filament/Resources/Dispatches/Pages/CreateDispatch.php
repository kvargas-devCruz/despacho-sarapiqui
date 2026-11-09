<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\Dispatches\Pages\Concerns\ReportsAssignmentErrors;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDispatch extends CreateRecord
{
    use ReportsAssignmentErrors;

    protected static string $resource = DispatchResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->withAssignmentErrors(fn () => parent::handleRecordCreation($data));
    }
}
