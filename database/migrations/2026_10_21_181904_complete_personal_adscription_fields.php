<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATALOGS = [
        'entidades_federativas' => 'id_entidad_federativa',
        'instituciones_salud' => 'id_institucion_salud',
        'programas' => 'id_programa',
        'vertientes' => 'id_vertiente',
        'tipos_campos_clinicos' => 'id_tipo_campo_clinico',
        'fuentes_financiamiento_beca' => 'id_fuente_financiamiento_beca',
    ];

    public function up(): void
    {
        foreach (self::CATALOGS as $name => $key) {
            Schema::create($name, function (Blueprint $table) use ($name, $key): void {
                $table->id($key);
                $table->string('nombre')->unique();
                if ($name === 'programas') {
                    $table->string('clave_programa', 50)->unique();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
        foreach (['municipios' => 'entidades_federativas', 'unidades_medicas' => 'instituciones_salud'] as $target => $catalog) {
            Schema::table($target, function (Blueprint $table) use ($catalog): void {
                $key = self::CATALOGS[$catalog];
                $table->foreignId($key)->nullable()->constrained($catalog, $key)->restrictOnDelete();
            });
        }
        Schema::table('adscripciones_personal', function (Blueprint $table): void {
            foreach (['programas', 'vertientes', 'tipos_campos_clinicos', 'fuentes_financiamiento_beca'] as $catalog) {
                $key = self::CATALOGS[$catalog];
                $table->foreignId($key)->nullable()->constrained($catalog, $key)->restrictOnDelete();
            }
            $table->string('numero_campo_clinico', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('adscripciones_personal', function (Blueprint $table): void {
            foreach (['programas', 'vertientes', 'tipos_campos_clinicos', 'fuentes_financiamiento_beca'] as $catalog) {
                $table->dropConstrainedForeignId(self::CATALOGS[$catalog]);
            }
            $table->dropColumn('numero_campo_clinico');
        });
        foreach (['municipios' => 'entidades_federativas', 'unidades_medicas' => 'instituciones_salud'] as $target => $catalog) {
            Schema::table($target, fn (Blueprint $table) => $table->dropConstrainedForeignId(self::CATALOGS[$catalog]));
        }
        foreach (array_reverse(array_keys(self::CATALOGS)) as $catalog) {
            Schema::dropIfExists($catalog);
        }
    }
};
