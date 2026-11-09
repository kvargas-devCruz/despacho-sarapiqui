<?php

namespace App\Filament\Widgets;

use App\Enums\DispatchStatus;
use App\Models\Dispatch;
use App\Support\Permissions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Escritorio: costo de operación de los servicios completados.
 */
class OperatingCostsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Costos y márgenes: solo administración y supervisión */
    public static function canView(): bool
    {
        return Permissions::canViewCosts();
    }

    protected ?string $heading = 'Costos de operación';

    protected ?string $description = 'Servicios completados. Valores de referencia configurables.';

    protected function getStats(): array
    {
        $month = Dispatch::where('status', DispatchStatus::Completed)
            ->whereNotNull('cost_total')
            ->where('completed_at', '>=', now()->startOfMonth());

        $today = (clone $month)->where('completed_at', '>=', today());

        $count = (clone $month)->count();
        $cost = (float) (clone $month)->sum('cost_total');
        $charge = (float) (clone $month)->sum('reference_charge');
        $km = (float) (clone $month)->sum('distance_km');

        $money = fn (float $amount) => '₡' . number_format($amount, 0, ',', '.');

        return [
            Stat::make('Costo de hoy', $money((float) $today->sum('cost_total')))
                ->description((clone $month)->where('completed_at', '>=', today())->count() . ' servicios completados hoy')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Costo del mes', $money($cost))
                ->description("{$count} servicios · " . number_format($km, 0, ',', '.') . ' km recorridos')
                ->icon('heroicon-o-calendar-days'),

            Stat::make('Costo promedio por servicio', $count ? $money($cost / $count) : '—')
                ->description($km > 0 ? $money($cost / $km) . ' por kilómetro' : 'Sin recorridos este mes')
                ->icon('heroicon-o-calculator'),

            Stat::make('Margen estimado del mes', $money($charge - $cost))
                ->description($charge > 0 ? round(($charge - $cost) / $charge * 100) . ' % frente a la tarifa de referencia' : '—')
                ->color($charge - $cost >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-arrow-trending-up'),
        ];
    }
}
