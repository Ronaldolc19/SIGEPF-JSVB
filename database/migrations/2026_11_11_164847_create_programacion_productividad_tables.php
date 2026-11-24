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
        foreach (['categorias_productividad' => 'id_categoria_productividad', 'unidades_medida_productividad' => 'id_unidad_medida'] as $name => $key) {
            Schema::create($name, function (Blueprint $table) use ($key): void {
                $table->id($key);
                $table->string('nombre', 150)->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::create('programacion_productividad', function (Blueprint $table): void {
            $table->id('id_programacion');
            $table->foreignId('id_promocion')->constrained('promociones', 'id_promocion')->restrictOnDelete();
            $table->foreignId('id_carrera')->constrained('carreras', 'id_carrera')->restrictOnDelete();
            $table->foreignId('id_unidad')->constrained('unidades_medicas', 'id_unidad')->restrictOnDelete();
            $table->foreignId('id_tipo_consulta')->constrained('tipos_consultas', 'id_tipo_consulta')->restrictOnDelete();
            $table->foreignId('id_categoria_productividad')->constrained('categorias_productividad', 'id_categoria_productividad')->restrictOnDelete();
            $table->foreignId('id_unidad_medida')->constrained('unidades_medida_productividad', 'id_unidad_medida')->restrictOnDelete();
            $table->string('actividad', 255);
            $table->string('categoria', 150);
            $table->string('unidad_medida', 150);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedInteger('meta_unidad')->nullable();
            $table->boolean('activa')->default(true);
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->index(['id_promocion', 'id_carrera', 'id_unidad'], 'programacion_destinatarios_index');
        });
        Schema::create('metas_productividad_personal', function (Blueprint $table): void {
            $table->id('id_meta_personal');
            $table->foreignId('id_programacion')->constrained('programacion_productividad', 'id_programacion')->restrictOnDelete();
            $table->foreignId('id_personal')->constrained('personal_formacion', 'id_personal')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->timestamps();
            $table->unique(['id_programacion', 'id_personal']);
        });
        Schema::table('productividad', function (Blueprint $table): void {
            $table->foreignId('id_programacion')->nullable()->constrained('programacion_productividad', 'id_programacion')->restrictOnDelete();
            $table->string('resultado', 30)->default('Realizada');
            $table->string('actividad_snapshot', 255)->nullable();
        });
        foreach (DB::table('tipos_consultas')->get(['id_tipo_consulta', 'nombre']) as $activity) {
            DB::table('productividad')->where('id_tipo_consulta', $activity->id_tipo_consulta)->update(['actividad_snapshot' => $activity->nombre]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productividad', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_programacion');
            $table->dropColumn(['resultado', 'actividad_snapshot']);
        });
        Schema::dropIfExists('metas_productividad_personal');
        Schema::dropIfExists('programacion_productividad');
        Schema::dropIfExists('unidades_medida_productividad');
        Schema::dropIfExists('categorias_productividad');
    }
};
