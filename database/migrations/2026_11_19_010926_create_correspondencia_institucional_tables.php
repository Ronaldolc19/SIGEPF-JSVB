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
        Schema::create('tipos_correspondencia', function (Blueprint $table): void {
            $table->id('id_tipo_correspondencia');
            $table->string('nombre', 150)->unique();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('correspondencia', function (Blueprint $table): void {
            $table->id('id_correspondencia');
            $table->foreignId('id_tipo_correspondencia')->constrained('tipos_correspondencia', 'id_tipo_correspondencia')->restrictOnDelete();
            $table->string('direccion', 20);
            $table->string('numero_oficio', 100)->nullable();
            $table->string('asunto', 255);
            $table->string('remitente', 255);
            $table->string('destinatario_externo', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_documento');
            $table->date('fecha_movimiento');
            $table->boolean('requiere_respuesta')->default(false);
            $table->date('fecha_limite')->nullable();
            $table->foreignId('responsable_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->string('estado', 30)->default('Borrador');
            $table->timestamp('publicado_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['estado', 'fecha_limite']);
        });
        Schema::create('destinatarios_correspondencia', function (Blueprint $table): void {
            $table->id('id_destinatario');
            $table->foreignId('id_correspondencia')->constrained('correspondencia', 'id_correspondencia')->restrictOnDelete();
            $table->foreignId('id_personal')->constrained('personal_formacion', 'id_personal')->restrictOnDelete();
            $table->timestamp('consultado_at')->nullable();
            $table->timestamp('confirmado_at')->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id_correspondencia', 'id_personal'], 'correspondencia_personal_unique');
        });
        Schema::create('archivos_correspondencia', function (Blueprint $table): void {
            $table->id('id_archivo');
            $table->foreignId('id_correspondencia')->constrained('correspondencia', 'id_correspondencia')->restrictOnDelete();
            $table->foreignId('id_adjunto_incidencia')->nullable()->unique()->constrained('adjuntos_incidencias', 'id_adjunto')->restrictOnDelete();
            $table->string('clase', 20);
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 1024)->nullable();
            $table->timestamps();
        });
        Schema::create('respuestas_correspondencia', function (Blueprint $table): void {
            $table->id('id_respuesta');
            $table->foreignId('id_correspondencia')->constrained('correspondencia', 'id_correspondencia')->restrictOnDelete();
            $table->foreignId('id_destinatario')->nullable()->constrained('destinatarios_correspondencia', 'id_destinatario')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('comentario');
            $table->string('nombre_archivo', 255)->nullable();
            $table->string('ruta_archivo', 1024)->nullable();
            $table->string('estado', 30)->default('Pendiente');
            $table->text('observaciones')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        Schema::create('historial_correspondencia', function (Blueprint $table): void {
            $table->id('id_historial');
            $table->foreignId('id_correspondencia')->constrained('correspondencia', 'id_correspondencia')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('evento', 100);
            $table->text('nota')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_correspondencia');
        Schema::dropIfExists('respuestas_correspondencia');
        Schema::dropIfExists('archivos_correspondencia');
        Schema::dropIfExists('destinatarios_correspondencia');
        Schema::dropIfExists('correspondencia');
        Schema::dropIfExists('tipos_correspondencia');
    }
};
