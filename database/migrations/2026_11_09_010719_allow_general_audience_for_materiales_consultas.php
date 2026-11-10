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
        Schema::table('materiales_consultas', function (Blueprint $table): void {
            $table->dropForeign(['id_tipo_personal']);
            $table->dropForeign(['id_carrera']);
        });
        Schema::table('materiales_consultas', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_tipo_personal')->nullable()->change();
            $table->unsignedBigInteger('id_carrera')->nullable()->change();
            $table->foreign('id_tipo_personal')->references('id_tipo_personal')->on('tipos_personal')->restrictOnDelete();
            $table->foreign('id_carrera')->references('id_carrera')->on('carreras')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('materiales_consultas')->whereNull('id_tipo_personal')->orWhereNull('id_carrera')->exists()) {
            throw new RuntimeException('Asigna destinatarios a todos los materiales antes de revertir esta migración.');
        }
        Schema::table('materiales_consultas', function (Blueprint $table): void {
            $table->dropForeign(['id_tipo_personal']);
            $table->dropForeign(['id_carrera']);
        });
        Schema::table('materiales_consultas', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_tipo_personal')->nullable(false)->change();
            $table->unsignedBigInteger('id_carrera')->nullable(false)->change();
            $table->foreign('id_tipo_personal')->references('id_tipo_personal')->on('tipos_personal')->restrictOnDelete();
            $table->foreign('id_carrera')->references('id_carrera')->on('carreras')->restrictOnDelete();
            $table->dropColumn('revision');
        });
    }
};
