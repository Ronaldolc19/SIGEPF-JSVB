<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConstanciaCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'entidades_federativas' => ['Michoacán'],
                'vertientes' => ['NA'],
                'tipos_campos_clinicos' => ['A', 'B'],
                'fuentes_financiamiento_beca' => ['Presupuesto federal', 'Otra institución'],
            ] as $table => $names) {
                foreach ($names as $name) {
                    if (! DB::table($table)->where('nombre', $name)->exists()) {
                        DB::table($table)->insert(['nombre' => $name, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        });
    }
}
