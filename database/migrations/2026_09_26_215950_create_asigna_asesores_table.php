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
        Schema::create('asigna_asesores', function (Blueprint $table) {
            $table->id('id_asigna_asesor');
            $table->foreignId('id_asesor');
            $table->foreignId('id_personal');
            $table->date('fecha_asignacion');
            $table->date('fecha_fin')->nullable();
            $table->text('motivo_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_asesor');
            $table->index('id_personal');

            $table->foreign('id_asesor')->references('id_asesor')->on('asesores')->restrictOnDelete();
            $table->foreign('id_personal')->references('id_personal')->on('personal_formacion')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asigna_asesores');
    }
};
