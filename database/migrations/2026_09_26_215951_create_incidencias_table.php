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
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id('id_incidencia');
            $table->foreignId('id_personal');
            $table->foreignId('id_tipo_incidencia');
            $table->date('fecha_hecho');
            $table->dateTime('fecha_registro');
            $table->string('evidencia', 1024)->nullable();
            $table->text('descripcion');
            $table->string('estado', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_personal');
            $table->index('id_tipo_incidencia');

            $table->foreign('id_personal')->references('id_personal')->on('personal_formacion')->restrictOnDelete();
            $table->foreign('id_tipo_incidencia')->references('id_tipo_incidencia')->on('tipos_incidencias')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
