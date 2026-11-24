<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductividadCatalogosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'categorias_productividad' => ['Detecciones', 'Inmunizaciones', 'Salud reproductiva', 'Servicios', 'Red de frío', 'Capacitación', 'Material educativo', 'Promoción de la salud', 'Atención clínica', 'Otros'],
            'unidades_medida_productividad' => ['Procedimientos', 'Sesiones', 'Dosis', 'Horas', 'Personas', 'Entregas', 'Registros', 'Materiales', 'Consultas'],
        ] as $table => $names) {
            foreach ($names as $name) {
                if (! DB::table($table)->where('nombre', $name)->exists()) {
                    DB::table($table)->insert(['nombre' => $name, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }
}
