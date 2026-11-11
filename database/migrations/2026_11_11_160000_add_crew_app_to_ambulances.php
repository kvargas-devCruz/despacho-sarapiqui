<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App de tripulación: cada unidad tiene su enlace privado (se instala en el
 * celular como app) y se guarda cuándo llegó el último GPS real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ambulances', function (Blueprint $table) {
            if (! Schema::hasColumn('ambulances', 'crew_token')) {
                $table->string('crew_token', 64)->nullable()->unique()->after('is_simulated');
            }
            if (! Schema::hasColumn('ambulances', 'crew_token_created_at')) {
                $table->timestamp('crew_token_created_at')->nullable()->after('crew_token');
            }
            if (! Schema::hasColumn('ambulances', 'gps_accuracy')) {
                $table->decimal('gps_accuracy', 7, 1)->nullable()->after('heading');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ambulances', function (Blueprint $table) {
            $table->dropUnique(['crew_token']);
            $table->dropColumn(['crew_token', 'crew_token_created_at', 'gps_accuracy']);
        });
    }
};
