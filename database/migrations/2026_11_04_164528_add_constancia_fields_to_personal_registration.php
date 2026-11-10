<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->foreignId('id_entidad_federativa')->nullable()->constrained('entidades_federativas', 'id_entidad_federativa')->restrictOnDelete();
        });
        Schema::table('instituciones', function (Blueprint $table): void {
            $table->string('clave_institucion', 50)->nullable()->unique();
        });
        Schema::table('personal_formacion', function (Blueprint $table): void {
            $table->unsignedTinyInteger('edad_constancia')->nullable();
        });
        Schema::table('datos_academicos', function (Blueprint $table): void {
            $table->dropForeign(['id_nivel_academico']);
        });
        Schema::table('datos_academicos', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_nivel_academico')->nullable()->change();
            $table->string('matricula', 100)->nullable()->change();
            $table->foreign('id_nivel_academico')->references('id_nivel_academico')->on('nivel_academico')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('datos_academicos')->whereNull('id_nivel_academico')->orWhereNull('matricula')->exists()) {
            throw new RuntimeException('Completa nivel académico y matrícula antes de revertir esta migración.');
        }
        Schema::table('datos_academicos', fn (Blueprint $table) => $table->dropForeign(['id_nivel_academico']));
        Schema::table('datos_academicos', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_nivel_academico')->nullable(false)->change();
            $table->string('matricula', 100)->nullable(false)->change();
            $table->foreign('id_nivel_academico')->references('id_nivel_academico')->on('nivel_academico')->restrictOnDelete();
        });
        Schema::table('personal_formacion', fn (Blueprint $table) => $table->dropColumn('edad_constancia'));
        Schema::table('instituciones', function (Blueprint $table): void {
            $table->dropUnique(['clave_institucion']);
            $table->dropColumn('clave_institucion');
        });
        Schema::table('personas', fn (Blueprint $table) => $table->dropConstrainedForeignId('id_entidad_federativa'));
    }
};
