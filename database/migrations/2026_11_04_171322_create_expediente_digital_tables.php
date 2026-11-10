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
        Schema::create('tipos_expediente', function (Blueprint $table): void {
            $table->id('id_tipo_expediente');
            $table->string('nombre', 255)->unique();
            $table->timestamps();
        });
        foreach ([
            'Carta de Adscripción y Aceptación', 'Carta de Asignación', 'Oficio de Presentación',
            'Oficios de Comisión', 'CURP', 'INE', 'Comprobante de domicilio',
            'Constancias de Cursos (Salud Mental es al inicio del servicio o internado)',
            'Oficios de Vacaciones', 'Incidencias (Incapacidades, llamadas de atención, etc.)',
        ] as $index => $name) {
            DB::table('tipos_expediente')->insert(['id_tipo_expediente' => $index + 1, 'nombre' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('documentos_expediente', function (Blueprint $table): void {
            $table->id('id_documento_expediente');
            $table->foreignId('id_personal')->constrained('personal_formacion', 'id_personal')->restrictOnDelete();
            $table->foreignId('id_tipo_expediente')->constrained('tipos_expediente', 'id_tipo_expediente')->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['id_personal', 'id_tipo_expediente']);
        });
        Schema::create('archivos_expediente', function (Blueprint $table): void {
            $table->id('id_archivo_expediente');
            $table->foreignId('id_documento_expediente')->constrained('documentos_expediente', 'id_documento_expediente')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 1024);
            $table->string('mime', 100);
            $table->unsignedBigInteger('tamano');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archivos_expediente');
        Schema::dropIfExists('documentos_expediente');
        Schema::dropIfExists('tipos_expediente');
    }
};
