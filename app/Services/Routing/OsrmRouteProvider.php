<?php

namespace App\Services\Routing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

/**
 * Rutas por carretera con OSRM, preparado para que el servicio falle.
 *
 * - Tiempo límite corto y un reintento solo si falló la conexión o el
 *   servidor (5xx), nunca si la petición estaba mal.
 * - Si aun así falla, se "abre el circuito": durante unos minutos ni se
 *   intenta y se responde al instante con la línea recta. Sin esto, con el
 *   servicio caído cada consulta se quedaba esperando varios segundos.
 * - Se valida la respuesta: una ruta vacía o con datos raros se descarta.
 */
class OsrmRouteProvider implements RouteProvider
{
    public const CIRCUIT_KEY = 'routing:osrm:down-until';

    public function route(array $from, array $to): RouteResult
    {
        if ($this->circuitIsOpen()) {
            return $this->straightLine($from, $to);
        }

        try {
            $points = $this->fetch($from, $to);

            return new RouteResult([$from, ...$points, $to], estimated: false, source: 'osrm');
        } catch (Throwable $exception) {
            $this->openCircuit($exception);

            return $this->straightLine($from, $to);
        }
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function fetch(array $from, array $to): array
    {
        $config = config('operations.routing');
        $url = rtrim($config['osrm_url'], '/') . "/route/v1/driving/{$from[1]},{$from[0]};{$to[1]},{$to[0]}";

        $response = Http::connectTimeout($config['connect_timeout'])
            ->timeout($config['timeout'])
            ->retry(
                $config['retries'] + 1,
                $config['retry_delay_ms'],
                fn (Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
            )
            ->get($url, ['overview' => 'full', 'geometries' => 'geojson'])
            ->throw();

        $coordinates = $response->json('routes.0.geometry.coordinates');

        if ($response->json('code') !== 'Ok' || ! is_array($coordinates) || count($coordinates) < 2) {
            throw new UnexpectedValueException('OSRM respondió sin una ruta válida.');
        }

        return array_map(function (mixed $pair): array {
            if (! is_array($pair) || ! is_numeric($pair[0] ?? null) || ! is_numeric($pair[1] ?? null)) {
                throw new UnexpectedValueException('OSRM respondió coordenadas inválidas.');
            }

            return [(float) $pair[1], (float) $pair[0]];
        }, $coordinates);
    }

    private function straightLine(array $from, array $to): RouteResult
    {
        return new RouteResult([$from, $to], estimated: true, source: 'straight_line');
    }

    public function circuitIsOpen(): bool
    {
        return Cache::get(self::CIRCUIT_KEY, 0) > now()->timestamp;
    }

    private function openCircuit(Throwable $exception): void
    {
        $seconds = config('operations.routing.cooldown_seconds');

        Cache::put(self::CIRCUIT_KEY, now()->addSeconds($seconds)->timestamp, $seconds);

        Log::warning("Servicio de rutas no disponible; se usa línea recta durante {$seconds} s.", [
            'error' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
