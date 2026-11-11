<?php

namespace App\Console\Commands;

use App\Services\FleetSimulator;
use Illuminate\Console\Command;

class SimulateFleet extends Command
{
    protected $signature = 'fleet:simulate
        {--tick=2 : Segundos entre cada actualización}
        {--speed=4 : Multiplicador de velocidad para la demo}
        {--auto : Genera emergencias automáticas y asigna la unidad más cercana}
        {--every=3 : Minutos entre emergencias automáticas}
        {--force : Correr aunque el sistema esté en producción}';

    protected $description = 'Simula el movimiento de las unidades por las rutas de Sarapiquí';

    public function handle(): int
    {
        // Con datos reales el simulador movería unidades reales en el mapa
        if (app()->isProduction() && ! config('app.demo') && ! $this->option('force')) {
            $this->error('El simulador está apagado en producción (APP_DEMO=false).');

            return self::FAILURE;
        }

        if (config('app.demo')) {
            $this->warn('APP_DEMO=true: el mapa ya ejecuta el simulador mientras está abierto. No es necesario correr este comando.');
        }

        $simulator = (new FleetSimulator(
            speedMultiplier: (float) $this->option('speed'),
            autoDispatch: (bool) $this->option('auto'),
            everyMinutes: (float) $this->option('every'),
        ))->onLog(fn (string $message) => $this->line($message));

        $this->info('🚑 Simulador de flota iniciado. Presiona Ctrl + C para detenerlo.');

        while (true) {
            $simulator->tick();
            sleep((int) $this->option('tick'));
        }
    }
}
