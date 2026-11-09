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

        // Vida máxima del enlace desde que se compartió, aunque el servicio
        // siga abierto (por ejemplo, si nadie lo cerró en el sistema)
        'max_hours' => (int) env('OPS_TRACKING_MAX_HOURS', 12),

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

    // Agenda de traslados programados y citas médicas
    'schedule' => [
        'transfer_speed_kmh' => 50,       // velocidad promedio en traslados no urgentes
        'default_travel_minutes' => 40,   // si no hay ubicación del paciente
        'boarding_minutes' => 10,         // subir al paciente a la unidad
        'arrival_buffer_minutes' => 15,   // llegar antes de la hora de la cita
        'handoff_minutes' => 20,          // entregar al paciente en el centro de salud
        'appointment_minutes' => 90,      // espera de la cita cuando hay regreso
    ],

    // Servicio de rutas por carretera (OSRM). El servidor público de OSRM es
    // solo para pruebas: para producción conviene uno propio o contratado.
    'routing' => [
        'osrm_url' => env('OPS_OSRM_URL', 'https://router.project-osrm.org'),
        'connect_timeout' => 2,   // segundos para conectar
        'timeout' => 4,           // segundos para responder
        'retries' => 1,           // reintentos si falla la conexión o el servidor
        'retry_delay_ms' => 300,
        // Si falla, durante este tiempo no se consulta y se usa línea recta
        'cooldown_seconds' => (int) env('OPS_ROUTING_COOLDOWN', 120),
    ],

];
