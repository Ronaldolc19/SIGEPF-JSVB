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
        Schema::create('productividad', function (Blueprint $table) {
            $table->id('id_productividad');
            $table->foreignId('id_personal');
            $table->foreignId('id_tipo_consulta');
            $table->date('fecha_actividad');
            $table->dateTime('fecha_registro');
            $table->string('evidencia', 1024)->nullable();
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('num_actividades');
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_personal');
            $table->index('id_tipo_consulta');

            $table->foreign('id_personal')->references('id_personal')->on('personal_formacion')->restrictOnDelete();
            $table->foreign('id_tipo_consulta')->references('id_tipo_consulta')->on('tipos_consultas')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productividad');
    }
};
