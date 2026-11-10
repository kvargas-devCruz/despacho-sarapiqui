<?php

namespace App\Filament\Widgets;

use App\Services\ResponseTimes;
use App\Support\Permissions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Escritorio: tiempos de respuesta de los últimos 30 días frente a las metas
 * de config/operations.php (response_targets).
 */
class ResponseTimesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Tiempos de respuesta';

    protected ?string $description = 'Últimos 30 días · mediana en minutos desde que entra la solicitud.';

    public static function canView(): bool
    {
        return Permissions::allows('metrics', 'view');
    }

    protected function getStats(): array
    {
        $summary = app(ResponseTimes::class)->summary(now()->subDays(30));
        $minutes = fn (?float $value) => $value === null ? '—' : number_format($value, 1, ',', '.') . ' min';
        $high = $summary['by_priority']['high'];

        return [
            Stat::make('Hasta asignar unidad', $minutes($summary['to_assign']))
                ->description("{$summary['services']} servicios en el periodo")
                ->icon('heroicon-o-clipboard-document-check'),

            Stat::make('Hasta llegar al paciente', $minutes($summary['to_arrive']))
                ->description('Alta prioridad: ' . $minutes($high['to_arrive']) . " (meta {$high['target']} min)")
                ->icon('heroicon-o-map-pin'),

            Stat::make('Llegadas dentro de la meta', $summary['on_target'] === null ? '—' : $summary['on_target'] . ' %')
                ->description('Según la prioridad de cada servicio')
                ->color(match (true) {
                    $summary['on_target'] === null => 'gray',
                    $summary['on_target'] >= 90 => 'success',
                    $summary['on_target'] >= 75 => 'warning',
                    default => 'danger',
                })
                ->icon('heroicon-o-flag'),

            Stat::make('Duración total del servicio', $minutes($summary['total']))
                ->description('Desde la llamada hasta el cierre')
                ->icon('heroicon-o-clock'),
        ];
    }
}
