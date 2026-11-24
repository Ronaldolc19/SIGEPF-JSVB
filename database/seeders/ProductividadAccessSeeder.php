<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class ProductividadAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['pasante', 'asesor'] as $role) {
            $ids = [];
            foreach (config('access.role_modules.'.$role.'.productividad') as $action) {
                $ids[] = Permiso::firstOrCreate(['codigo' => 'productividad.'.$action], ['nombre' => ucfirst($action).' · Productividad semanal'])->id;
            }
            Rol::where('codigo', $role)->firstOrFail()->permisos()->syncWithoutDetaching($ids);
        }
    }
}
