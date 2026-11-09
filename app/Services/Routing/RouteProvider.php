<?php

namespace App\Services\Routing;

/**
 * Servicio de rutas por carretera. Hoy se usa OSRM; se puede cambiar por otro
 * proveedor (Google, Mapbox, un OSRM propio) desde config/operations.php sin
 * tocar el resto del sistema.
 */
interface RouteProvider
{
    /**
     * Nunca lanza errores por fallas de red: si el servicio no responde,
     * devuelve una ruta estimada en línea recta.
     *
     * @param  array{0: float, 1: float}  $from  [latitud, longitud]
     * @param  array{0: float, 1: float}  $to  [latitud, longitud]
     */
    public function route(array $from, array $to): RouteResult;
}
