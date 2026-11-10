<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpedienteAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Rol::where('codigo', 'pasante')->firstOrFail();
            $ids = [];
            foreach (['ver', 'registrar', 'eliminar'] as $action) {
                $ids[] = Permiso::firstOrCreate(['codigo' => 'expedientes.'.$action], ['nombre' => ucfirst($action).' · Expediente digital'])->id;
            }
            $role->permisos()->syncWithoutDetaching($ids);
        });
    }
}
