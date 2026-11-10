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
        Schema::create('unidades_medicas', function (Blueprint $table) {
            $table->id('id_unidad');
            $table->foreignId('id_jurisdiccion');
            $table->foreignId('id_localidad');
            $table->foreignId('id_tipo_unidad');
            $table->foreignId('id_tipologia');
            $table->string('clave_clues', 20)->unique();
            $table->string('nom_unidad', 255);
            $table->string('domicilio', 255);
            $table->string('referencias', 255)->nullable();
            $table->char('cp', 5);
            $table->string('horario', 255);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_jurisdiccion');
            $table->index('id_localidad');
            $table->index('id_tipo_unidad');
            $table->index('id_tipologia');

            $table->foreign('id_jurisdiccion')->references('id_jurisdiccion')->on('jurisdicciones')->restrictOnDelete();
            $table->foreign('id_localidad')->references('id_localidad')->on('localidades')->restrictOnDelete();
            $table->foreign('id_tipo_unidad')->references('id_tipo_unidad')->on('tipo_unidades')->restrictOnDelete();
            $table->foreign('id_tipologia')->references('id_tipologia')->on('tipologias_unidades')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_medicas');
    }
};
