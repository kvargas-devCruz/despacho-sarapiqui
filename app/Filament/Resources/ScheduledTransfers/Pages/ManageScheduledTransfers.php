<?php

namespace App\Filament\Resources\ScheduledTransfers\Pages;

use App\Filament\Resources\ScheduledTransfers\ScheduledTransferResource;
use App\Models\ScheduledTransfer;
use App\Services\TransferSchedule;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ManageScheduledTransfers extends ManageRecords
{
    protected static string $resource = ScheduledTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agendar traslado')
                ->using(function (array $data) {
                    $repeat = (bool) Arr::pull($data, 'repeat', false);
                    $days = array_map('intval', Arr::pull($data, 'repeat_days', []) ?? []);
                    $until = Arr::pull($data, 'repeat_until');
                    $schedule = app(TransferSchedule::class);

                    if ($repeat && $days && $until) {
                        $series = $schedule->createSeries($data, $days, Carbon::parse($until));

                        Notification::make()->title("Se agendaron {$series->count()} traslados")->success()->send();

                        return $series->first();
                    }

                    $transfer = ScheduledTransfer::create($data);
                    $warnings = $schedule->conflicts($transfer);

                    if ($warnings) {
                        Notification::make()
                            ->title('Traslado agendado con avisos')
                            ->body(implode(' ', $warnings))
                            ->warning()
                            ->persistent()
                            ->send();
                    }

                    return $transfer;
                }),
        ];
    }
}
