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
        Schema::table('tipos_consultas', function (Blueprint $table) {
            $table->foreignId('id_carrera')->nullable()->constrained('carreras', 'id_carrera')->restrictOnDelete();
        });
        Schema::table('productividad', function (Blueprint $table) {
            $table->string('estado', 30)->default('Registrada');
            $table->text('observaciones')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_revision')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->index(['id_personal', 'fecha_actividad']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productividad', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->dropIndex(['id_personal', 'fecha_actividad']);
            $table->dropColumn(['estado', 'observaciones', 'revisado_por', 'fecha_revision', 'revision']);
        });
        Schema::table('tipos_consultas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_carrera');
        });
    }
};
