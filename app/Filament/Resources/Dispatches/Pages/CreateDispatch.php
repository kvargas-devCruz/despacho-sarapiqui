<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\Dispatches\Pages\Concerns\ReportsAssignmentErrors;
use App\Models\Patient;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDispatch extends CreateRecord
{
    use ReportsAssignmentErrors;

    protected static string $resource = DispatchResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $dispatch = $this->withAssignmentErrors(fn () => parent::handleRecordCreation($data));

        // Recuerda al paciente para reconocerlo la próxima vez que llamen
        Patient::rememberFrom($dispatch, $this->data['patient_national_id'] ?? null);

        return $dispatch;
    }
}
