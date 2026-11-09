<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles del despacho. Los usuarios nuevos empiezan como "Solo consulta"
 * (el mínimo permiso) y un administrador les da el rol que corresponde.
 * Los usuarios que ya existían quedan como administradores para que nadie
 * pierda el acceso al publicar este cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer')->after('email');
        });

        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
