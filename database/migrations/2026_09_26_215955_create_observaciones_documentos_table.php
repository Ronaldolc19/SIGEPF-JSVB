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
        Schema::create('observaciones_documentos', function (Blueprint $table) {
            $table->id('id_revision_doc');
            $table->foreignId('id_documento');
            $table->foreignId('user_id');
            $table->foreignId('id_asesor')->nullable();
            $table->text('observacion');
            $table->dateTime('fecha_observacion');
            $table->string('estado', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_documento');
            $table->index('user_id');
            $table->index('id_asesor');

            $table->foreign('id_documento')->references('id_documento')->on('documentos_investigacion')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('id_asesor')->references('id_asesor')->on('asesores')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observaciones_documentos');
    }
};
