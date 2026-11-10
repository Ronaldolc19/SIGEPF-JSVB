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
        Schema::create('localidades', function (Blueprint $table) {
            $table->id('id_localidad');
            $table->foreignId('id_municipio');
            $table->string('nom_loc', 255);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_municipio');
            $table->foreign('id_municipio')->references('id_municipio')->on('municipios')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('localidades');
    }
};
