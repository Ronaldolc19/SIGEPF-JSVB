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
        Schema::create('personas', function (Blueprint $table) {
            $table->id('id_persona');
            $table->foreignId('id_localidad')->nullable();
            $table->foreignId('id_genero')->nullable();
            $table->foreignId('id_nacionalidad')->nullable();
            $table->string('nombre', 255);
            $table->string('ap', 255);
            $table->string('am', 255)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('curp', 18)->nullable()->unique();
            $table->string('rfc', 13)->nullable();
            $table->string('desc_domicilio', 255)->nullable();
            $table->string('correo', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_localidad');
            $table->index('id_genero');
            $table->index('id_nacionalidad');

            $table->foreign('id_localidad')->references('id_localidad')->on('localidades')->restrictOnDelete();
            $table->foreign('id_genero')->references('id_genero')->on('generos')->restrictOnDelete();
            $table->foreign('id_nacionalidad')->references('id_nacionalidad')->on('nacionalidades')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
