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
        Schema::create('personal_formacion', function (Blueprint $table) {
            $table->id('id_personal');
            $table->foreignId('id_persona');
            $table->foreignId('id_tipo_personal');
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->string('estado', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_persona');
            $table->index('id_tipo_personal');

            $table->foreign('id_persona')->references('id_persona')->on('personas')->restrictOnDelete();
            $table->foreign('id_tipo_personal')->references('id_tipo_personal')->on('tipos_personal')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_formacion');
    }
};
