<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos del traslado y avisos al hospital.
 *
 * - dispatch_documents: orden médica u otros documentos (fotos o PDF) que se
 *   suben desde el panel o desde el celular de la tripulación. Disco privado.
 * - received_*: quién recibió al paciente en el hospital, con su firma.
 * - hospitals.email: a dónde se avisa que va un paciente en camino.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dispatch_documents')) {
            Schema::create('dispatch_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
                $table->string('type', 30);
                $table->string('path');
                $table->string('original_name');
                $table->string('mime', 100);
                $table->unsignedInteger('size');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('uploaded_via', 10)->default('panel');
                $table->timestamps();
            });
        }

        Schema::table('dispatches', function (Blueprint $table) {
            if (! Schema::hasColumn('dispatches', 'received_by_name')) {
                $table->string('received_by_name', 120)->nullable()->after('completed_at');
                $table->string('received_by_role', 40)->nullable()->after('received_by_name');
                $table->string('received_signature_path')->nullable()->after('received_by_role');
                $table->timestamp('received_at')->nullable()->after('received_signature_path');
                $table->timestamp('hospital_notified_at')->nullable()->after('received_at');
            }
        });

        Schema::table('hospitals', function (Blueprint $table) {
            if (! Schema::hasColumn('hospitals', 'email')) {
                $table->string('email', 150)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_documents');

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['received_by_name', 'received_by_role', 'received_signature_path', 'received_at', 'hospital_notified_at']);
        });

        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
