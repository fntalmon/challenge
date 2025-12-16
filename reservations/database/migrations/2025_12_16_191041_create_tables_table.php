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
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->enum('location', ['A', 'B', 'C', 'D']);
            $table->integer('table_number');
            $table->integer('capacity'); // cantidad de personas
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            
            // Índices para búsquedas optimizadas
            $table->unique(['location', 'table_number']);
            $table->index(['location', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
