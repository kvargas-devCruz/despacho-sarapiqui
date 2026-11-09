<?php

namespace App\Services\Routing;

/**
 * Ruta entre dos puntos.
 *
 * $estimated = true cuando no se pudo consultar el servicio de rutas y se usó
 * la línea recta; así la pantalla puede avisar que el recorrido es aproximado.
 */
final readonly class RouteResult
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $points  [latitud, longitud]
     */
    public function __construct(
        public array $points,
        public bool $estimated,
        public string $source,
    ) {}
}
