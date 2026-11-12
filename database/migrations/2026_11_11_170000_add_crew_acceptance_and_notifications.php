<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tripulación acepta o rechaza el servicio antes de salir, y el despacho
 * recibe el aviso en la campana del panel (notificaciones guardadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            if (! Schema::hasColumn('dispatches', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('assigned_at');
            }
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
