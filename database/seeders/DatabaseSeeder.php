<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(AcademicoAccessSeeder::class);
        $this->call(CorrespondenciaSeeder::class);
        $this->call(ProductividadAccessSeeder::class);
        $this->call(ProductividadCatalogosSeeder::class);
        $this->call(ConsultaAccessSeeder::class);
        $this->call(ExpedienteAccessSeeder::class);
        $this->call(IncidenciaCatalogSeeder::class);
        $this->call(ValleBravoCatalogSeeder::class);
        $this->call(ConstanciaCatalogSeeder::class);
        $this->call(PersonalFormacionDemoSeeder::class);
    }
}
