<?php

/*
|--------------------------------------------------------------------------
| Costos y seguimiento de los servicios
|--------------------------------------------------------------------------
|
| Valores de referencia para la demo. Se ajustan aquí (o en el .env) con
| los datos reales de la empresa sin tocar el código.
|
*/

return [

    // Seguimiento compartido con el paciente, la familia o el hospital
    'tracking' => [
        // Horas que el enlace sigue abierto después de terminar el servicio
        'hours_after_completion' => 2,

        // Velocidad promedio para estimar la hora de llegada (km/h)
        'average_speed_kmh' => 45,

        // Las carreteras no son líneas rectas: factor sobre la distancia directa
        'road_factor' => 1.3,
    ],

    // Costo de operación por servicio (colones)
    'costs' => [
        'fuel_price_per_liter' => (float) env('OPS_FUEL_PRICE', 760),
        'km_per_liter' => (float) env('OPS_KM_PER_LITER', 7),
        'crew_hourly_cost' => (float) env('OPS_CREW_HOURLY', 9500),   // tripulación completa por hora
        'maintenance_per_km' => (float) env('OPS_MAINTENANCE_KM', 85), // llantas, aceite, desgaste

        // Insumos médicos promedio por tipo de servicio
        'supplies' => [
            'emergency' => 18000,
            'transfer' => 6000,
        ],

        // Si la unidad no registró recorrido (por ejemplo, datos viejos),
        // se estima: ida hasta el paciente + traslado al hospital
        'approach_km_estimate' => 8,
    ],

    // Tarifa de referencia para estimar el margen (no es una factura)
    'tariffs' => [
        'emergency' => ['base' => 65000, 'per_km' => 900],
        'transfer' => ['base' => 45000, 'per_km' => 750],
    ],

];
