<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recepción guiada de la llamada.
 *
 * - chief_complaint: motivo principal elegido de la lista del guion.
 * - triage: respuestas a las preguntas del guion (sí / no).
 * - suggested_priority: la prioridad que sugirió el sistema con esas respuestas.
 * - priority_note: por qué el despacho eligió una prioridad más baja que la sugerida.
 * - location_confirmed: el despacho confirmó el pin del mapa con quien llama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            if (! Schema::hasColumn('dispatches', 'chief_complaint')) {
                $table->string('chief_complaint', 40)->nullable()->after('priority');
            }
            if (! Schema::hasColumn('dispatches', 'triage')) {
                $table->json('triage')->nullable()->after('chief_complaint');
            }
            if (! Schema::hasColumn('dispatches', 'suggested_priority')) {
                $table->string('suggested_priority', 10)->nullable()->after('triage');
            }
            if (! Schema::hasColumn('dispatches', 'priority_note')) {
                $table->string('priority_note', 255)->nullable()->after('suggested_priority');
            }
            if (! Schema::hasColumn('dispatches', 'location_confirmed')) {
                $table->boolean('location_confirmed')->default(false)->after('pickup_longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['chief_complaint', 'triage', 'suggested_priority', 'priority_note', 'location_confirmed']);
        });
    }
};
