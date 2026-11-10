<?php

/*
|--------------------------------------------------------------------------
| Matriz de permisos por rol
|--------------------------------------------------------------------------
|
| Única fuente de verdad de quién puede hacer qué en el despacho. Las
| policies de app/Policies, los menús, los botones y las URLs directas
| leen de aquí.
|
| Roles: admin (Administrador), supervisor (Supervisor de turno),
| dispatcher (Despachador) y viewer (Solo consulta: ve el mapa y los
| servicios, por ejemplo gerencia o un hospital aliado).
|
| Para cambiar un permiso se edita este archivo y se corre la prueba
| tests/Feature/Security/PermissionMatrixTest.php.
*/

$all = ['admin', 'supervisor', 'dispatcher', 'viewer'];
$operations = ['admin', 'supervisor', 'dispatcher'];
$management = ['admin', 'supervisor'];

return [

    // Servicios: el despacho los crea y actualiza; borrar es de supervisión
    // (lo normal es cancelar, así queda el registro)
    'dispatches' => ['view' => $all, 'create' => $operations, 'update' => $operations, 'delete' => $management],

    // Agenda de traslados programados y citas médicas
    'scheduled_transfers' => ['view' => $all, 'create' => $operations, 'update' => $operations, 'delete' => $management],

    // Pacientes frecuentes: datos personales y de salud, solo el equipo operativo
    'patients' => ['view' => $operations, 'create' => $operations, 'update' => $operations, 'delete' => ['admin']],

    // Bitácora del servicio: se escribe sola y no se edita
    'dispatch_events' => ['view' => $all],

    // Métricas de tiempos de respuesta
    'metrics' => ['view' => $all],

    // Enlace público de seguimiento para el paciente, la familia o el hospital
    'tracking' => ['create' => $operations, 'delete' => $operations],

    // Flota y catálogos
    'ambulances' => ['view' => $all, 'create' => $management, 'update' => $management, 'delete' => ['admin']],
    'ambulance_locations' => ['view' => $all],
    'hospitals' => ['view' => $all, 'create' => $management, 'update' => $management, 'delete' => ['admin']],

    // Costos y márgenes del negocio
    'costs' => ['view' => $management],

    // Usuarios del sistema
    'users' => ['view' => ['admin'], 'create' => ['admin'], 'update' => ['admin'], 'delete' => ['admin']],

];
