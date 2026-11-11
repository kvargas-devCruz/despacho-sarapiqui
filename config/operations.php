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

    // Protección de datos de pacientes
    'security' => [
        // Llave de la huella de cédulas; si queda vacía se usa APP_KEY
        'blind_index_key' => env('OPS_BLIND_INDEX_KEY'),
    ],

    // Posibles solicitudes duplicadas: mismo teléfono, mismo paciente o muy
    // cerca de un servicio activo creado hace poco
    'duplicates' => [
        'minutes' => 60,
        'meters' => 300,
    ],

    // Metas de tiempo de respuesta en minutos (desde que entra la solicitud
    // hasta que la unidad llega), por prioridad
    'response_targets' => [
        'high' => (int) env('OPS_TARGET_HIGH', 15),
        'medium' => (int) env('OPS_TARGET_MEDIUM', 30),
        'low' => (int) env('OPS_TARGET_LOW', 90),
    ],

    // Recepción guiada de emergencias: motivos del guion con su prioridad base
    // y preguntas de sí / no que pueden subirla. El sistema sugiere la mayor.
    'intake' => [
        'complaints' => [
            'chest_pain' => ['label' => 'Dolor en el pecho', 'priority' => 'high'],
            'breathing' => ['label' => 'Dificultad para respirar', 'priority' => 'high'],
            'unconscious' => ['label' => 'Inconsciente o desmayo', 'priority' => 'high'],
            'stroke' => ['label' => 'Posible derrame (cara, brazo o habla)', 'priority' => 'high'],
            'seizure' => ['label' => 'Convulsión', 'priority' => 'high'],
            'trauma' => ['label' => 'Accidente de tránsito o caída', 'priority' => 'medium'],
            'bleeding' => ['label' => 'Herida o sangrado', 'priority' => 'medium'],
            'pregnancy' => ['label' => 'Embarazo o parto', 'priority' => 'medium'],
            'illness' => ['label' => 'Fiebre o malestar general', 'priority' => 'low'],
            'other' => ['label' => 'Otro motivo', 'priority' => 'medium'],
        ],

        // 'when' es la respuesta que hace subir la prioridad
        'questions' => [
            'conscious' => ['label' => '¿Está consciente y responde?', 'when' => false, 'priority' => 'high', 'reason' => 'no responde'],
            'breathing' => ['label' => '¿Respira con normalidad?', 'when' => false, 'priority' => 'high', 'reason' => 'no respira con normalidad'],
            'severe_bleeding' => ['label' => '¿Hay sangrado abundante que no se detiene?', 'when' => true, 'priority' => 'high', 'reason' => 'sangrado abundante'],
            'multiple_patients' => ['label' => '¿Hay más de un paciente?', 'when' => true, 'priority' => 'high', 'reason' => 'varios pacientes'],
            'scene_danger' => ['label' => '¿Hay peligro en la escena (fuego, armas, tránsito)?', 'when' => true, 'priority' => null, 'reason' => 'peligro en la escena: coordinar con el 9-1-1 antes de enviar la unidad'],
        ],

        // Menores de 1 año y mayores de esta edad no bajan de prioridad media
        'vulnerable_age' => 75,
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
