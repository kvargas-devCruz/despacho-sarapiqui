<?php

namespace App\Services;

use App\Enums\DispatchPriority;
use App\Enums\DispatchStatus;
use App\Models\Dispatch;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Métricas de tiempos de respuesta del despacho.
 *
 * Se calculan con las horas que el sistema ya guarda en cada servicio:
 * entrada (created_at), asignación (assigned_at), llegada (on_scene_at) y
 * cierre (completed_at). Los promedios usan la mediana, que no se deforma
 * por un servicio que quedó abierto por error.
 */
class ResponseTimes
{
    /**
     * @return array{
     *     services: int,
     *     to_assign: ?float,
     *     to_arrive: ?float,
     *     total: ?float,
     *     on_target: ?float,
     *     by_priority: array<string, array{services: int, to_arrive: ?float, target: int, on_target: ?float}>
     * }
     */
    public function summary(CarbonInterface $from, ?CarbonInterface $to = null): array
    {
        $dispatches = Dispatch::query()
            ->where('created_at', '>=', $from)
            ->when($to, fn ($query) => $query->where('created_at', '<', $to))
            ->where('status', '!=', DispatchStatus::Cancelled)
            ->get(['id', 'priority', 'created_at', 'assigned_at', 'on_scene_at', 'completed_at']);

        $byPriority = [];

        foreach (DispatchPriority::cases() as $priority) {
            $group = $dispatches->where('priority', $priority);
            $target = (int) config("operations.response_targets.{$priority->value}");

            $byPriority[$priority->value] = [
                'services' => $group->count(),
                'to_arrive' => $this->median($group, 'on_scene_at'),
                'target' => $target,
                'on_target' => $this->onTargetRate($group, $target),
            ];
        }

        $arrived = $dispatches->filter(fn (Dispatch $dispatch) => $dispatch->on_scene_at !== null);
        $onTarget = $arrived->filter(fn (Dispatch $dispatch) => $this->minutes($dispatch, 'on_scene_at') <= (int) config("operations.response_targets.{$dispatch->priority->value}"));

        return [
            'services' => $dispatches->count(),
            'to_assign' => $this->median($dispatches, 'assigned_at'),
            'to_arrive' => $this->median($dispatches, 'on_scene_at'),
            'total' => $this->median($dispatches, 'completed_at'),
            'on_target' => $arrived->isEmpty() ? null : round($onTarget->count() / $arrived->count() * 100),
            'by_priority' => $byPriority,
        ];
    }

    /** Minutos desde que entró la solicitud hasta el momento indicado */
    public function minutes(Dispatch $dispatch, string $moment): ?float
    {
        return $dispatch->{$moment} ? round($dispatch->created_at->diffInSeconds($dispatch->{$moment}) / 60, 1) : null;
    }

    /** @param  Collection<int, Dispatch>  $dispatches */
    private function median(Collection $dispatches, string $moment): ?float
    {
        $values = $dispatches->map(fn (Dispatch $dispatch) => $this->minutes($dispatch, $moment))->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : round((float) $values->median(), 1);
    }

    /** @param  Collection<int, Dispatch>  $dispatches */
    private function onTargetRate(Collection $dispatches, int $target): ?float
    {
        $arrived = $dispatches->filter(fn (Dispatch $dispatch) => $dispatch->on_scene_at !== null);

        if ($arrived->isEmpty()) {
            return null;
        }

        return round($arrived->filter(fn (Dispatch $dispatch) => $this->minutes($dispatch, 'on_scene_at') <= $target)->count() / $arrived->count() * 100);
    }
}
