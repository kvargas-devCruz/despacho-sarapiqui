<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento compartido y costo de cada servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('code');
            $table->timestamp('tracking_shared_at')->nullable()->after('tracking_token');

            $table->decimal('distance_km', 8, 1)->nullable()->after('cancelled_at');
            $table->boolean('distance_estimated')->default(false)->after('distance_km');
            $table->unsignedInteger('duration_minutes')->nullable()->after('distance_estimated');
            $table->decimal('cost_fuel', 12, 2)->nullable()->after('duration_minutes');
            $table->decimal('cost_crew', 12, 2)->nullable()->after('cost_fuel');
            $table->decimal('cost_maintenance', 12, 2)->nullable()->after('cost_crew');
            $table->decimal('cost_supplies', 12, 2)->nullable()->after('cost_maintenance');
            $table->decimal('cost_total', 12, 2)->nullable()->after('cost_supplies');
            $table->decimal('reference_charge', 12, 2)->nullable()->after('cost_total');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropColumn([
                'tracking_token', 'tracking_shared_at', 'distance_km', 'distance_estimated', 'duration_minutes',
                'cost_fuel', 'cost_crew', 'cost_maintenance', 'cost_supplies', 'cost_total', 'reference_charge',
            ]);
        });
    }
};
