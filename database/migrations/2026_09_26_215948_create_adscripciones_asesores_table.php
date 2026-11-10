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
        Schema::create('adscripciones_asesores', function (Blueprint $table) {
            $table->id('id_adscripcion');
            $table->foreignId('id_asesor');
            $table->foreignId('id_unidad');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('motivo_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_asesor');
            $table->index('id_unidad');

            $table->foreign('id_asesor')->references('id_asesor')->on('asesores')->restrictOnDelete();
            $table->foreign('id_unidad')->references('id_unidad')->on('unidades_medicas')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adscripciones_asesores');
    }
};
