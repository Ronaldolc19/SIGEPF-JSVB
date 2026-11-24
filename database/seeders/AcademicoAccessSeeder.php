<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicoAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['pasante' => ['ver', 'registrar'], 'asesor' => ['ver', 'revisar']] as $role => $actions) {
                $rol = Rol::where('codigo', $role)->firstOrFail();
                foreach ($actions as $action) {
                    $permission = Permiso::firstOrCreate(['codigo' => 'academico.'.$action], ['nombre' => ucfirst($action).' · Repositorio académico']);
                    $rol->permisos()->syncWithoutDetaching([$permission->id]);
                }
            }
        });
    }
}
