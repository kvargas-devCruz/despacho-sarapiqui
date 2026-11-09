<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda de traslados programados y citas médicas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('series_id')->nullable()->index();
            $table->string('patient_name');
            $table->string('patient_phone', 30)->nullable();
            $table->string('patient_national_id', 30)->nullable();
            $table->string('reason')->nullable();
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->dateTime('appointment_at');
            $table->dateTime('pickup_at');
            $table->dateTime('busy_until');
            $table->boolean('round_trip')->default(false);
            $table->string('ambulance_type')->default('transfer');
            $table->foreignId('ambulance_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('scheduled');
            $table->foreignId('dispatch_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pickup_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_transfers');
    }
};
