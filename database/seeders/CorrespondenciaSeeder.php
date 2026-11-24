<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CorrespondenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['ver', 'registrar'] as $action) {
                $permission = Permiso::firstOrCreate(['codigo' => 'correspondencia.'.$action], ['nombre' => ucfirst($action).' · Correspondencia']);
                Rol::where('codigo', 'pasante')->firstOrFail()->permisos()->syncWithoutDetaching([$permission->id]);
            }
            foreach (['Oficio', 'Circular', 'Convocatoria', 'Solicitud', 'Respuesta institucional', 'Comunicación de seguimiento'] as $name) {
                if (! DB::table('tipos_correspondencia')->where('nombre', $name)->exists()) {
                    DB::table('tipos_correspondencia')->insert(['nombre' => $name, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
