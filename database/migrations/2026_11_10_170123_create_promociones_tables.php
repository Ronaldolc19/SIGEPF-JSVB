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
        Schema::create('promociones', function (Blueprint $table): void {
            $table->id('id_promocion');
            $table->string('nombre', 150)->unique();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('Programada');
            $table->boolean('permite_inscripciones')->default(true);
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['estado', 'fecha_inicio']);
        });
        Schema::create('promocion_carrera', function (Blueprint $table): void {
            $table->foreignId('id_promocion')->constrained('promociones', 'id_promocion')->restrictOnDelete();
            $table->foreignId('id_carrera')->constrained('carreras', 'id_carrera')->restrictOnDelete();
            $table->primary(['id_promocion', 'id_carrera']);
        });
        Schema::table('personal_formacion', function (Blueprint $table): void {
            $table->foreignId('id_promocion')->nullable()->constrained('promociones', 'id_promocion')->restrictOnDelete();
            $table->text('motivo_periodo')->nullable();
        });
        Schema::create('historial_periodos_personal', function (Blueprint $table): void {
            $table->id('id_historial_periodo');
            $table->foreignId('id_personal')->constrained('personal_formacion', 'id_personal')->restrictOnDelete();
            $table->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_promocion')->nullable()->constrained('promociones', 'id_promocion')->restrictOnDelete();
            $table->date('inicio_anterior')->nullable();
            $table->date('termino_anterior')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_termino')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_periodos_personal');
        Schema::table('personal_formacion', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_promocion');
            $table->dropColumn('motivo_periodo');
        });
        Schema::dropIfExists('promocion_carrera');
        Schema::dropIfExists('promociones');
    }
};
