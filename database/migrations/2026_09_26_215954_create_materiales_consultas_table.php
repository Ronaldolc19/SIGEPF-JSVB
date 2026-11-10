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
        Schema::create('materiales_consultas', function (Blueprint $table) {
            $table->id('id_material');
            $table->foreignId('id_tipo_documento');
            $table->foreignId('id_tipo_personal');
            $table->foreignId('id_carrera');
            $table->string('titulo', 255);
            $table->text('descripcion')->nullable();
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 1024);
            $table->dateTime('fecha_publicacion')->nullable();
            $table->string('estado', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_tipo_documento');
            $table->index('id_tipo_personal');
            $table->index('id_carrera');

            $table->foreign('id_tipo_documento')->references('id_tipo_documento')->on('tipos_documentos')->restrictOnDelete();
            $table->foreign('id_tipo_personal')->references('id_tipo_personal')->on('tipos_personal')->restrictOnDelete();
            $table->foreign('id_carrera')->references('id_carrera')->on('carreras')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materiales_consultas');
    }
};
