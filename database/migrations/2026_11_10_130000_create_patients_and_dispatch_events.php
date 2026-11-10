<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacientes frecuentes y bitácora de cada servicio.
 *
 * - patients: personas que ya se atendieron. Se reconocen por el teléfono de
 *   quien llama o por la cédula (cifrada, se busca por su huella).
 * - dispatches.caller_phone / patient_id: quién llamó y a qué paciente se atendió.
 * - dispatch_events: cada cambio de estado del servicio, con hora y usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->text('national_id')->nullable();
            $table->string('national_id_hash', 64)->nullable()->unique();
            $table->string('phone', 20)->nullable()->index();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Lo que el despacho debe saber antes de salir: oxígeno, silla de ruedas, acceso difícil...
            $table->text('care_notes')->nullable();
            $table->timestamps();
        });

        Schema::table('dispatches', function (Blueprint $table) {
            $table->string('caller_phone', 20)->nullable()->after('priority')->index();
            $table->foreignId('patient_id')->nullable()->after('caller_phone')->constrained()->nullOnDelete();
        });

        Schema::create('dispatch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['dispatch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_events');

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
            $table->dropIndex(['caller_phone']);
            $table->dropColumn('caller_phone');
        });

        Schema::dropIfExists('patients');
    }
};
