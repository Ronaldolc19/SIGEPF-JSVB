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
        Schema::create('tipos_incidencias', function (Blueprint $table) {
            $table->id('id_tipo_incidencia');
            $table->foreignId('id_categoria');
            $table->string('nombre', 255);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_categoria');
            $table->foreign('id_categoria')->references('id_categoria')->on('categorias')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_incidencias');
    }
};
