<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->integer('party_size'); // cantidad de personas
            $table->enum('location', ['A', 'B', 'C', 'D']); // asignada automáticamente
            $table->integer('duration_minutes')->default(120); // 2 horas por default
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('confirmed');
            $table->timestamps();
            
            // Índices para queries optimizadas
            $table->index(['reservation_date', 'location']);
            $table->index(['user_id', 'reservation_date']);
        });
        
        // Tabla pivot para mesas asignadas (máximo 3 mesas por reserva)
        Schema::create('reservation_table', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->foreignId('table_id')->constrained('tables')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['reservation_id', 'table_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_table');
        Schema::dropIfExists('reservations');
    }
};
