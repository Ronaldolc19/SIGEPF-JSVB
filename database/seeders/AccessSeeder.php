<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(RolSeeder::class);
            $ids = [];
            foreach (config('access.modules') as $module => $definition) {
                foreach ($definition['acciones'] as $action) {
                    $permission = Permiso::firstOrCreate(['codigo' => $module.'.'.$action], [
                        'nombre' => ucfirst($action).' · '.$definition['nombre'],
                    ]);
                    $ids[] = $permission->id;
                }
            }
            Rol::where('codigo', 'administrador')->firstOrFail()->permisos()->syncWithoutDetaching($ids);
        });
    }
}
