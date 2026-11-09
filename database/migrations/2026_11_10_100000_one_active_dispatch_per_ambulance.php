<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una ambulancia no puede tener dos servicios activos al mismo tiempo.
 *
 * La regla vive en la propia base de datos: una columna calculada guarda la
 * unidad solo mientras el servicio está activo (pendiente con unidad, asignado
 * o en curso) y un índice único impide que se repita. Así, aunque dos
 * despachadores den clic al mismo tiempo o haya un error en el código, la base
 * de datos rechaza la segunda asignación.
 */
return new class extends Migration
{
    private const ACTIVE = "('pending', 'assigned', 'in_progress')";

    public function up(): void
    {
        // Antes de crear la regla, se liberan las asignaciones duplicadas que
        // hayan quedado: la unidad se queda con su servicio más reciente y los
        // anteriores vuelven a "pendiente" sin unidad para reasignarlos.
        $duplicated = DB::table('dispatches')
            ->select('ambulance_id')
            ->whereNotNull('ambulance_id')
            ->whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->groupBy('ambulance_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('ambulance_id');

        foreach ($duplicated as $ambulanceId) {
            $keep = DB::table('dispatches')
                ->where('ambulance_id', $ambulanceId)
                ->whereIn('status', ['pending', 'assigned', 'in_progress'])
                ->max('id');

            DB::table('dispatches')
                ->where('ambulance_id', $ambulanceId)
                ->whereIn('status', ['pending', 'assigned', 'in_progress'])
                ->where('id', '!=', $keep)
                ->update(['ambulance_id' => null, 'status' => 'pending', 'assigned_at' => null]);
        }

        Schema::table('dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('active_ambulance_id')
                ->nullable()
                ->virtualAs('CASE WHEN status IN ' . self::ACTIVE . ' THEN ambulance_id END');
        });

        Schema::table('dispatches', function (Blueprint $table) {
            $table->unique('active_ambulance_id');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropUnique(['active_ambulance_id']);
        });

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn('active_ambulance_id');
        });
    }
};
