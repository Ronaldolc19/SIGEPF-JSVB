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
        Schema::create('documentos_investigacion', function (Blueprint $table) {
            $table->id('id_documento');
            $table->foreignId('id_personal');
            $table->foreignId('id_tipo_documento');
            $table->foreignId('id_documento_anterior')->nullable()->unique();
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 1024);
            $table->unsignedInteger('version');
            $table->string('estado', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_personal');
            $table->index('id_tipo_documento');

            $table->foreign('id_personal')->references('id_personal')->on('personal_formacion')->restrictOnDelete();
            $table->foreign('id_tipo_documento')->references('id_tipo_documento')->on('tipos_documentos')->restrictOnDelete();
            $table->foreign('id_documento_anterior')->references('id_documento')->on('documentos_investigacion')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_investigacion');
    }
};
