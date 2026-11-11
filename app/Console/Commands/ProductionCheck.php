<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\DemoData;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Lista de revisión antes de usar el sistema con datos reales. Solo lee:
 * no cambia nada. Devuelve error si algo no está listo, para poder usarlo
 * en los comandos de publicación.
 */
class ProductionCheck extends Command
{
    protected $signature = 'ops:production-check';

    protected $description = 'Revisa si el sistema está listo para datos reales (no cambia nada)';

    public function handle(DemoData $demo): int
    {
        $demoUsers = $demo->users();
        $realAdmins = User::where('role', UserRole::Admin)->whereNotIn('id', $demoUsers->pluck('id'))->count();
        $units = $demo->simulatedUnits()->count();
        $osrm = (string) config('operations.routing.osrm_url');
        $mailer = (string) config('mail.default');

        $checks = [
            ['Ambiente de producción (APP_ENV=production)', app()->isProduction(), 'Poner APP_ENV=production'],
            ['Errores internos ocultos (APP_DEBUG=false)', ! config('app.debug'), 'Poner APP_DEBUG=false'],
            ['Modo demo apagado (APP_DEMO=false)', ! config('app.demo'), 'Poner APP_DEMO=false: quita el usuario precargado y el simulador'],
            ['Sin usuarios de demostración', $demoUsers->isEmpty(), $demoUsers->isEmpty() ? '' : 'Quedan ' . $demoUsers->pluck('email')->implode(', ') . ' → php artisan ops:remove-demo'],
            ['Sin unidades del simulador', $units === 0, "Quedan {$units} unidades simuladas → php artisan ops:remove-demo"],
            ['Al menos un administrador real', $realAdmins > 0, 'Crear uno: php artisan make:filament-user y darle rol admin en Usuarios'],
            ['Servidor de rutas propio', ! Str::contains($osrm, config('operations.demo.public_osrm')), 'El OSRM público es solo para pruebas: poner OPS_OSRM_URL'],
            ['Correo real configurado', ! in_array($mailer, ['log', 'array'], true), "MAIL_MAILER={$mailer}: los correos no salen"],
            ['Dirección segura (https)', Str::startsWith((string) config('app.url'), 'https://'), 'APP_URL debe empezar con https://'],
        ];

        $failed = 0;

        $this->table(['', 'Revisión', 'Qué hacer'], array_map(function (array $check) use (&$failed) {
            [$label, $ok, $fix] = $check;
            $failed += $ok ? 0 : 1;

            return [$ok ? '✓' : '✗', $label, $ok ? '' : $fix];
        }, $checks));

        if ($failed > 0) {
            $this->error("{$failed} " . ($failed === 1 ? 'punto pendiente' : 'puntos pendientes') . ' antes de usar datos reales.');

            return self::FAILURE;
        }

        $this->info('Todo listo para datos reales.');

        return self::SUCCESS;
    }
}
