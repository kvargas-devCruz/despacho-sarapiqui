<?php

namespace App\Console\Commands;

use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\Patient;
use App\Models\ScheduledTransfer;
use App\Support\DemoData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quita los datos de demostración para empezar a usar el sistema en real:
 * usuarios demo, unidades del simulador y los servicios, citas y pacientes
 * de ejemplo. Conserva los centros de salud y las unidades reales.
 *
 * No corre si alguien que no es de la demo ya trabajó en el sistema.
 */
class RemoveDemoData extends Command
{
    protected $signature = 'ops:remove-demo {--force : No pedir confirmación}';

    protected $description = 'Quita usuarios, unidades, servicios, citas y pacientes de demostración';

    public function handle(DemoData $demo): int
    {
        if ($demo->hasRealActivity()) {
            $this->error('Ya hay servicios atendidos por usuarios reales: no se borra nada. Quite los datos de demo a mano o pida ayuda.');

            return self::FAILURE;
        }

        $users = $demo->users();
        $units = $demo->simulatedUnits();

        $this->table(['Se va a quitar', 'Cantidad'], [
            ['Usuarios de demostración', $users->count()],
            ['Unidades del simulador', $units->count()],
            ['Servicios (con su bitácora)', Dispatch::count()],
            ['Citas de traslado', ScheduledTransfer::count()],
            ['Pacientes frecuentes', Patient::count()],
        ]);
        $this->line('Se conservan los centros de salud y las unidades reales (' . Ambulance::where('is_simulated', false)->count() . ').');

        if (! $this->option('force') && ! $this->confirm('¿Quitar estos datos? No se puede deshacer.')) {
            $this->warn('No se cambió nada.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($users, $units) {
            ScheduledTransfer::query()->delete();
            Dispatch::query()->delete();          // la bitácora se borra en cascada
            Patient::query()->delete();
            Ambulance::whereKey($units->pluck('id'))->delete();
            $users->each->delete();
        });

        $this->info('Datos de demostración quitados. Revise con: php artisan ops:production-check');

        return self::SUCCESS;
    }
}
