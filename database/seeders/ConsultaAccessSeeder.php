<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsultaAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $permission = Permiso::firstOrCreate(['codigo' => 'consulta.ver'], ['nombre' => 'Ver · Portal de consulta']);
            Rol::where('codigo', 'pasante')->firstOrFail()->permisos()->syncWithoutDetaching([$permission->id]);
            foreach (['Guía', 'Formato', 'Manual', 'Instructivo'] as $name) {
                if (! DB::table('tipos_documentos')->where('nombre', $name)->exists()) {
                    DB::table('tipos_documentos')->insert(['nombre' => $name, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
