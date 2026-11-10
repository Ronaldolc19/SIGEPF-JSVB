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
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->date('fecha_fin')->nullable();
            $table->foreignId('id_adscripcion')->nullable()->constrained('adscripciones_personal', 'id_adscripcion')->restrictOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('resultado')->nullable();
            $table->dateTime('fecha_cierre')->nullable();
            $table->unsignedInteger('revision')->default(0);
        });
        Schema::create('seguimientos_incidencias', function (Blueprint $table): void {
            $table->id('id_seguimiento');
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('estado_anterior', 50);
            $table->string('estado_nuevo', 50);
            $table->text('nota');
            $table->timestamps();
        });
        Schema::create('adjuntos_incidencias', function (Blueprint $table): void {
            $table->id('id_adjunto');
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_archivo_expediente')->nullable()->constrained('archivos_expediente', 'id_archivo_expediente')->restrictOnDelete();
            $table->string('clase', 20);
            $table->string('numero_oficio', 100)->nullable();
            $table->string('asunto', 255);
            $table->date('fecha_oficio')->nullable();
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 1024)->nullable();
            $table->timestamps();
            $table->unique(['id_incidencia', 'id_archivo_expediente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjuntos_incidencias');
        Schema::dropIfExists('seguimientos_incidencias');
        Schema::table('incidencias', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_adscripcion');
            $table->dropConstrainedForeignId('registrado_por');
            $table->dropColumn(['fecha_fin', 'resultado', 'fecha_cierre', 'revision']);
        });
    }
};
