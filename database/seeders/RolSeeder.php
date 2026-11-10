<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'administrador' => 'Administrador',
            'asesor' => 'Asesor',
            'pasante' => 'Pasante',
        ] as $codigo => $nombre) {
            Rol::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);
        }
    }
}
