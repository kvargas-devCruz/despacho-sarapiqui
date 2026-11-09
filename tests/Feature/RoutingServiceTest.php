<?php

use App\Services\Routing\OsrmRouteProvider;
use App\Services\Routing\RouteProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| Fallos del servicio de rutas (OSRM): sin conexión, errores del servidor,
| respuestas dañadas y recuperación. Nunca se llama al servicio real.
*/

beforeEach(function () {
    Cache::flush();
    config(['operations.routing.retry_delay_ms' => 0]);
});

$from = [10.4560, -84.0190];
$to = [10.4470, -84.0780];

function rutaOsrm(): array
{
    return ['code' => 'Ok', 'routes' => [['geometry' => ['coordinates' => [[-84.0190, 10.4560], [-84.05, 10.45], [-84.0780, 10.4470]]]]]];
}

it('usa la ruta por carretera cuando el servicio responde', function () use ($from, $to) {
    Http::fake(['router.project-osrm.org/*' => Http::response(rutaOsrm())]);

    $route = app(RouteProvider::class)->route($from, $to);

    expect($route->estimated)->toBeFalse()
        ->and($route->source)->toBe('osrm')
        ->and($route->points)->toHaveCount(5)
        ->and($route->points[2])->toBe([10.45, -84.05]);
});

it('sin conexión usa línea recta en lugar de fallar', function () use ($from, $to) {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

    $route = app(RouteProvider::class)->route($from, $to);

    expect($route->estimated)->toBeTrue()
        ->and($route->points)->toBe([$from, $to]);
});

it('reintenta una vez si el servidor falla y aprovecha si se recupera', function () use ($from, $to) {
    Http::fakeSequence('router.project-osrm.org/*')
        ->push('Bad Gateway', 502)
        ->push(rutaOsrm());

    $route = app(RouteProvider::class)->route($from, $to);

    expect($route->estimated)->toBeFalse();
    Http::assertSentCount(2);
});

it('no reintenta si el error es de la petición (4xx)', function () use ($from, $to) {
    Http::fake(['router.project-osrm.org/*' => Http::response(['code' => 'InvalidQuery'], 400)]);

    expect(app(RouteProvider::class)->route($from, $to)->estimated)->toBeTrue();
    Http::assertSentCount(1);
});

it('descarta respuestas dañadas o sin ruta', function (mixed $body) use ($from, $to) {
    Http::fake(['router.project-osrm.org/*' => Http::response($body)]);

    expect(app(RouteProvider::class)->route($from, $to)->estimated)->toBeTrue();
})->with([
    'sin rutas' => [['code' => 'NoRoute', 'routes' => []]],
    'coordenadas vacías' => [['code' => 'Ok', 'routes' => [['geometry' => ['coordinates' => []]]]]],
    'coordenadas con texto' => [['code' => 'Ok', 'routes' => [['geometry' => ['coordinates' => [['a', 'b'], ['c', 'd']]]]]]],
    'no es JSON' => ['<html>Service Unavailable</html>'],
]);

it('tras una falla deja de consultar unos minutos y luego vuelve a intentar', function () use ($from, $to) {
    $serviceIsDown = true;
    Http::fake(function () use (&$serviceIsDown) {
        if ($serviceIsDown) {
            throw new ConnectionException('timeout');
        }

        return Http::response(rutaOsrm());
    });
    $routing = app(RouteProvider::class);

    $routing->route($from, $to);
    $attempts = count(Http::recorded());

    // Mientras el circuito está abierto responde al instante sin llamar al servicio
    foreach (range(1, 5) as $i) {
        expect($routing->route($from, $to)->estimated)->toBeTrue();
    }
    expect(count(Http::recorded()))->toBe($attempts)
        ->and(app(OsrmRouteProvider::class)->circuitIsOpen())->toBeTrue();

    // Pasado el tiempo de espera, si el servicio volvió, se usa de nuevo
    $this->travel(config('operations.routing.cooldown_seconds') + 1)->seconds();
    $serviceIsDown = false;

    expect($routing->route($from, $to)->estimated)->toBeFalse();
});
