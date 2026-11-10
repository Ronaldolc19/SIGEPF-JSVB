<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncidenciaCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'Asistencia' => ['Inasistencia', 'Retardo', 'Salida anticipada'],
                'Salud' => ['Ausencia por enfermedad', 'Incapacidad'],
                'Permisos y actividades autorizadas' => ['Permiso personal', 'Trámite académico', 'Comisión', 'Vacaciones'],
                'Conducta o cumplimiento' => ['Reporte de incumplimiento'],
            ] as $category => $types) {
                $id = DB::table('categorias')->where('nombre', $category)->value('id_categoria')
                    ?? DB::table('categorias')->insertGetId(['nombre' => $category, 'created_at' => now(), 'updated_at' => now()]);
                foreach ($types as $type) {
                    if (! DB::table('tipos_incidencias')->where('id_categoria', $id)->where('nombre', $type)->exists()) {
                        DB::table('tipos_incidencias')->insert(['id_categoria' => $id, 'nombre' => $type, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
            foreach (['pasante' => ['ver'], 'asesor' => ['ver', 'registrar', 'editar', 'revisar']] as $code => $actions) {
                $role = Rol::where('codigo', $code)->firstOrFail();
                $ids = [];
                foreach ($actions as $action) {
                    $ids[] = Permiso::firstOrCreate(['codigo' => 'incidencias.'.$action], ['nombre' => ucfirst($action).' · Incidencias'])->id;
                }
                $role->permisos()->syncWithoutDetaching($ids);
            }
        });
    }
}
