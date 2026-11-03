<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambulance_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('speed')->nullable();
            $table->float('heading')->nullable();
            $table->float('accuracy')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['ambulance_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_locations');
    }
};