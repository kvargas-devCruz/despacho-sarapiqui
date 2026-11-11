# Despacho Sarapiquí

Sistema de despacho y rastreo de ambulancias en tiempo real, hecho con **Laravel 13 + Filament 5**.

[![Pruebas](../../actions/workflows/tests.yml/badge.svg)](../../actions/workflows/tests.yml)

![Mapa en vivo](docs/screenshots/mapa.png)

**Incluye:**
- mapa en vivo de las unidades;
- servicios de emergencia y traslado;
- agenda de citas médicas;
- enlace de seguimiento para el paciente o la familia;
- costo por servicio;
- un simulador que mueve las unidades por las rutas reales de Sarapiquí.

*Datos de unidades y pacientes ficticios.*

## Instalación local

Requisitos: PHP 8.4+, Composer, Node 20+ y SQLite (en Windows, Laravel Herd trae todo).

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
```

Usuarios de demostración (contraseña `demo1234`, **solo para pruebas**):

| Usuario | Rol | Qué puede hacer |
|---|---|---|
| demo@despacho.cr | Administrador | Todo, incluidos usuarios |
| supervisor@despacho.cr | Supervisor de turno | Todo lo operativo, flota, hospitales y costos |
| despachador@despacho.cr | Despachador | Crear y asignar servicios, agenda y seguimiento |
| consulta@despacho.cr | Solo consulta | Ver mapa, servicios y agenda |

Usuario propio:

```bash
php artisan make:filament-user                       # queda como "Solo consulta"
php artisan user:role correo@empresa.cr admin        # admin, supervisor, dispatcher, viewer
```

Simulador (en otra terminal): `php artisan fleet:simulate --auto`

## Pruebas

```bash
php artisan test
```

En GitHub las pruebas corren solas en cada `git push`, con SQLite y con **MySQL 8.4**. Los resultados quedan en la pestaña **Actions**. Entre otras cosas, cubren:

- **Permisos:** cada rol, contra cada URL y botón (`tests/Feature/Security`).
- **Una ambulancia, un servicio:** formulario, bloqueo al guardar, índice único en la base de datos y simulador (`UnitAvailabilityTest`).
- **Enlaces de seguimiento:** vencimiento, anulación y datos que muestran (`TrackingLinksTest`).
- **Recepción guiada:** prioridad sugerida, campos obligatorios por tipo y punto en el mapa (`GuidedIntakeTest`).
- **Fallos del servicio de rutas:** sin conexión, errores 5xx, respuestas dañadas y recuperación (`RoutingServiceTest`).

## Reglas importantes

- **Una ambulancia no puede tener dos servicios activos.** El formulario solo ofrece unidades libres. Al guardar, se bloquea la unidad y se revisa otra vez. Además, la base de datos lo impide con la columna `active_ambulance_id` y su índice único.
- **Recepción guiada:** el formulario sigue el guion de la llamada (tipo, quién llama, ubicación, motivo y prioridad). En una emergencia son obligatorios el teléfono, el motivo, las preguntas de sí / no, el punto en el mapa y confirmarlo con quien llama. El sistema sugiere la prioridad (`config/operations.php`, `intake`); si el despacho elige una más baja, debe anotar por qué. Un traslado pide el nombre del paciente y el centro de destino.
- **Ubicación en el mapa:** se toca el mapa o se arrastra el pin; "Buscar la dirección" usa OpenStreetMap (solo Costa Rica). Los puntos fuera del país se rechazan.
- **Agenda:** la misma unidad no puede estar en dos traslados que se cruzan. Si a una hora hay más traslados que unidades del tipo, solo se avisa.
- **Seguimiento:** el enlace se cierra 2 h después de terminar el servicio y vence a las 12 h de compartido (`OPS_TRACKING_MAX_HOURS`). Se puede anular. Nunca muestra el nombre ni la dirección escrita del paciente.
- **Servicio de rutas (OSRM):**
  - Tiene tiempo límite y un reintento.
  - Si falla, durante 2 min (`OPS_ROUTING_COOLDOWN`) se usa línea recta sin esperar.
  - Para cambiar de proveedor, se implementa `App\Services\Routing\RouteProvider`.
  - El servidor público de OSRM es solo para pruebas: en producción conviene `OPS_OSRM_URL` propio.

## Publicar en Laravel Cloud

1. `git push` a `main` → Cloud publica y corre las migraciones.
2. Variables:
   - `APP_DEBUG=false`
   - `APP_LOCALE=es`
   - **`APP_DEMO=false`**: con `true`, el simulador avanza cuando alguien abre un enlace público.
   - `DB_CONNECTION=mysql`
3. Cambiar o desactivar los usuarios de demostración antes de usar datos reales.

> **Nunca** correr `php artisan migrate:fresh` en producción.

## Mantenimiento

| Tarea | Cómo |
|---|---|
| Cambiar un permiso | `config/permissions.php` y correr `php artisan test tests/Feature/Security` |
| Tarifas, costos y tiempos | `config/operations.php` (o variables `OPS_*`) |
| Ver por qué una ruta salió en línea recta | `storage/logs/laravel.log`: "Servicio de rutas no disponible" |
| Liberar una unidad "trabada" | Completar o cancelar su servicio activo desde Servicios |

## Autor

**Kevin Vargas Cruz**
