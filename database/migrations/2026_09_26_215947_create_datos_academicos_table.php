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
        Schema::create('datos_academicos', function (Blueprint $table) {
            $table->id('id_dat_academico');
            $table->foreignId('id_personal')->unique();
            $table->foreignId('id_institucion');
            $table->foreignId('id_carrera');
            $table->foreignId('id_nivel_academico');
            $table->string('matricula', 100);
            $table->decimal('promedio', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_institucion');
            $table->index('id_carrera');
            $table->index('id_nivel_academico');

            $table->foreign('id_personal')->references('id_personal')->on('personal_formacion')->restrictOnDelete();
            $table->foreign('id_institucion')->references('id_institucion')->on('instituciones')->restrictOnDelete();
            $table->foreign('id_carrera')->references('id_carrera')->on('carreras')->restrictOnDelete();
            $table->foreign('id_nivel_academico')->references('id_nivel_academico')->on('nivel_academico')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_academicos');
    }
};
