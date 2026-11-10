<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonalFormacionDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $nationality = $this->id('nacionalidades', 'id_nacionalidad', 'Mexicana');
            $gender = $this->id('generos', 'id_genero', 'No especificado');
            $type = $this->id('tipos_personal', 'id_tipo_personal', 'Pasante de servicio social');
            $institution = $this->id('instituciones', 'id_institucion', 'Universidad Autónoma del Estado de México');
            $level = $this->id('nivel_academico', 'id_nivel_academico', 'Licenciatura');
            $program = $this->id('programas', 'id_programa', 'Servicio social de Medicina');
            $vertiente = $this->id('vertientes', 'id_vertiente', 'Servicio social');
            $fieldType = $this->id('tipos_campos_clinicos', 'id_tipo_campo_clinico', 'Consulta externa');
            $funding = $this->id('fuentes_financiamiento_beca', 'id_fuente_financiamiento_beca', 'Beca estatal');
            $unit = DB::table('unidades_medicas')->where('clave_clues', 'MCSSA007982')->value('id_unidad');
            $records = [
                ['nombre' => 'Mariana Fernanda', 'ap' => 'López', 'am' => 'Ramírez', 'curp' => 'LORM000315MMCPMRA8', 'rfc' => 'LORM000315AB1', 'fecha_nacimiento' => '2000-03-15', 'localidad' => 'Valle de Bravo', 'carrera' => 'Medicina', 'matricula' => 'UAEM-SS-001', 'promedio' => 9.2, 'correo' => 'mariana.lopez.demo@example.test'],
                ['nombre' => 'Carlos Eduardo', 'ap' => 'Martínez', 'am' => 'Sánchez', 'curp' => 'MASC010722HMCRNRA4', 'rfc' => 'MASC010722CD2', 'fecha_nacimiento' => '2001-07-22', 'localidad' => 'Amanalco de Becerra', 'carrera' => 'Enfermería', 'matricula' => 'UAEM-SS-002', 'promedio' => 8.8, 'correo' => 'carlos.martinez.demo@example.test'],
                ['nombre' => 'Paola Andrea', 'ap' => 'Hernández', 'am' => 'Gómez', 'curp' => 'HEGP990911MMCRML6', 'rfc' => 'HEGP990911EF3', 'fecha_nacimiento' => '1999-09-11', 'localidad' => 'Colorines', 'carrera' => 'Odontología', 'matricula' => 'UAEM-SS-003', 'promedio' => 9.5, 'correo' => 'paola.hernandez.demo@example.test'],
            ];
            foreach ($records as $data) {
                $locality = DB::table('localidades')->where('nom_loc', $data['localidad'])->value('id_localidad');
                $career = $this->id('carreras', 'id_carrera', $data['carrera']);
                DB::table('personas')->updateOrInsert(['curp' => $data['curp']], ['id_localidad' => $locality, 'id_genero' => $gender, 'id_nacionalidad' => $nationality, 'nombre' => $data['nombre'], 'ap' => $data['ap'], 'am' => $data['am'], 'fecha_nacimiento' => $data['fecha_nacimiento'], 'rfc' => $data['rfc'], 'desc_domicilio' => 'Domicilio de demostración, '.$data['localidad'], 'correo' => $data['correo'], 'telefono' => '722000'.random_int(1000, 9999), 'updated_at' => now(), 'created_at' => now()]);
                $person = DB::table('personas')->where('curp', $data['curp'])->value('id_persona');
                DB::table('personal_formacion')->updateOrInsert(['id_persona' => $person, 'id_tipo_personal' => $type], ['fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2027-07-31', 'estado' => 'En formacion', 'updated_at' => now(), 'created_at' => now()]);
                $personal = DB::table('personal_formacion')->where('id_persona', $person)->where('id_tipo_personal', $type)->value('id_personal');
                DB::table('datos_academicos')->updateOrInsert(['id_personal' => $personal], ['id_institucion' => $institution, 'id_carrera' => $career, 'id_nivel_academico' => $level, 'matricula' => $data['matricula'], 'promedio' => $data['promedio'], 'updated_at' => now(), 'created_at' => now()]);
                DB::table('adscripciones_personal')->updateOrInsert(['id_personal' => $personal, 'id_unidad' => $unit], ['fecha_inicio' => '2026-08-01', 'fecha_fin' => null, 'id_programa' => $program, 'id_vertiente' => $vertiente, 'id_tipo_campo_clinico' => $fieldType, 'id_fuente_financiamiento_beca' => $funding, 'numero_campo_clinico' => 'VB-DEMO-'.$personal, 'updated_at' => now(), 'created_at' => now()]);
            }
        });
    }

    private function id(string $table, string $key, string $name): int
    {
        return (int) DB::table($table)->where('nombre', $name)->value($key);
    }
}
