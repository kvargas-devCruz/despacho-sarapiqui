<?php

namespace App\Services;

use App\Enums\DispatchPriority;

/**
 * Sugiere la prioridad de una emergencia a partir del guion de la llamada:
 * el motivo principal, las respuestas de sí / no y la edad del paciente.
 *
 * La decisión final es del despacho; si elige una prioridad más baja que la
 * sugerida debe anotar por qué (queda en el servicio).
 */
class Triage
{
    /**
     * @param  array<string, bool|string|null>  $answers
     * @return array{priority: ?DispatchPriority, reasons: list<string>, warnings: list<string>}
     */
    public function evaluate(?string $complaint, array $answers, ?int $age = null): array
    {
        $config = config('operations.intake');
        $priority = null;
        $reasons = [];
        $warnings = [];

        if ($complaint && isset($config['complaints'][$complaint])) {
            $item = $config['complaints'][$complaint];
            $priority = DispatchPriority::from($item['priority']);
            $reasons[] = mb_strtolower($item['label']);
        }

        foreach ($config['questions'] as $key => $question) {
            $answer = self::toBool($answers[$key] ?? null);

            if ($answer === null || $answer !== $question['when']) {
                continue;
            }

            if ($question['priority'] === null) {
                $warnings[] = ucfirst($question['reason']);

                continue;
            }

            $priority = self::higher($priority, DispatchPriority::from($question['priority']));
            $reasons[] = $question['reason'];
        }

        if ($age !== null && ($age < 1 || $age >= $config['vulnerable_age'])) {
            if ($priority === DispatchPriority::Low) {
                $priority = DispatchPriority::Medium;
                $reasons[] = $age < 1 ? 'menor de 1 año' : "{$age} años";
            }
        }

        return ['priority' => $priority, 'reasons' => array_values(array_unique($reasons)), 'warnings' => $warnings];
    }

    /** Texto corto para mostrar al despacho: "Alta: no respira con normalidad, dolor en el pecho" */
    public function summary(?string $complaint, array $answers, ?int $age = null): ?string
    {
        $result = $this->evaluate($complaint, $answers, $age);

        if (! $result['priority']) {
            return null;
        }

        return $result['priority']->getLabel() . ': ' . implode(', ', $result['reasons']) . '.';
    }

    /** ¿La prioridad elegida es más baja que la sugerida? */
    public static function isBelow(DispatchPriority|string|null $chosen, DispatchPriority|string|null $suggested): bool
    {
        if (! $chosen || ! $suggested) {
            return false;
        }

        $chosen = $chosen instanceof DispatchPriority ? $chosen : DispatchPriority::from($chosen);
        $suggested = $suggested instanceof DispatchPriority ? $suggested : DispatchPriority::from($suggested);

        return self::rank($chosen) < self::rank($suggested);
    }

    /** @return array<string, string> */
    public static function complaintOptions(): array
    {
        return collect(config('operations.intake.complaints'))->map(fn (array $item) => $item['label'])->all();
    }

    private static function higher(?DispatchPriority $a, DispatchPriority $b): DispatchPriority
    {
        return $a && self::rank($a) >= self::rank($b) ? $a : $b;
    }

    private static function rank(DispatchPriority $priority): int
    {
        return match ($priority) {
            DispatchPriority::Low => 1,
            DispatchPriority::Medium => 2,
            DispatchPriority::High => 3,
        };
    }

    private static function toBool(mixed $value): ?bool
    {
        return match (true) {
            $value === null, $value === '' => null,
            is_bool($value) => $value,
            default => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        };
    }
}
