<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ValleBravoCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::table('entidades_federativas')->updateOrInsert(['nombre' => 'Estado de México'], ['updated_at' => now(), 'created_at' => now()]);
            $entity = DB::table('entidades_federativas')->where('nombre', 'Estado de México')->value('id_entidad_federativa');
            $municipios = [];
            foreach (['Valle de Bravo', 'Amanalco', 'Donato Guerra', 'Ixtapan del Oro', 'Santo Tomás', 'Villa de Allende', 'Temascaltepec', 'Otzoloapan', 'Zacazonapan'] as $name) {
                DB::table('municipios')->updateOrInsert(['nom_mun' => $name], ['id_entidad_federativa' => $entity, 'updated_at' => now(), 'created_at' => now()]);
                $municipios[$name] = DB::table('municipios')->where('nom_mun', $name)->value('id_municipio');
            }
            $localidades = ['Valle de Bravo' => ['Valle de Bravo', 'Avándaro', 'Colorines', 'Santa María Ahuacatlán', 'Cuadrilla de Dolores'], 'Amanalco' => ['Amanalco de Becerra', 'San Bartolo', 'San Juan'], 'Donato Guerra' => ['Villa Donato Guerra', 'San Antonio de la Laguna'], 'Ixtapan del Oro' => ['Ixtapan del Oro', 'Miahuatlán de Hidalgo'], 'Santo Tomás' => ['Santo Tomás de los Plátanos', 'Ocampo'], 'Villa de Allende' => ['Villa de Allende', 'Bosencheve'], 'Temascaltepec' => ['Temascaltepec de González', 'San Pedro Tenayac'], 'Otzoloapan' => ['Otzoloapan'], 'Zacazonapan' => ['Zacazonapan']];
            $locality = [];
            foreach ($localidades as $municipio => $names) {
                foreach ($names as $name) {
                    DB::table('localidades')->updateOrInsert(['id_municipio' => $municipios[$municipio], 'nom_loc' => $name], ['updated_at' => now(), 'created_at' => now()]);
                    $locality[$name] = DB::table('localidades')->where('id_municipio', $municipios[$municipio])->where('nom_loc', $name)->value('id_localidad');
                }
            }
            $jurisdiction = DB::table('jurisdicciones')->where('nombre', 'Valle de Bravo')->value('id_jurisdiccion') ?? DB::table('jurisdicciones')->insertGetId(['nombre' => 'Valle de Bravo', 'created_at' => now(), 'updated_at' => now()]);
            $institutions = [];
            foreach (['Secretaría de Salud del Estado de México', 'Instituto Mexicano del Seguro Social'] as $name) {
                DB::table('instituciones_salud')->updateOrInsert(['nombre' => $name], ['updated_at' => now(), 'created_at' => now()]);
                $institutions[$name] = DB::table('instituciones_salud')->where('nombre', $name)->value('id_institucion_salud');
            }
            $types = $this->ids('tipo_unidades', 'id_tipo_unidad', ['Hospital general', 'Unidad de medicina familiar']);
            $topologies = $this->ids('tipologias_unidades', 'id_tipologia', ['Hospital', 'Urbana']);
            foreach ([['MCSSA007982', 'H.G. Valle de Bravo', 'Valle de Bravo', 'Secretaría de Salud del Estado de México', 'Hospital general', 'Hospital', 'Avenida Fray Gregorio Jiménez de la Cuenca, Barrio San Antonio', '51200'], ['MCIMS000903', 'UMF 233 Valle Bravo', 'Valle de Bravo', 'Instituto Mexicano del Seguro Social', 'Unidad de medicina familiar', 'Urbana', 'Ignacio Zaragoza 111, Colonia Centro', '51200'], ['MCIMS000915', 'UMF 234 Colorines', 'Colorines', 'Instituto Mexicano del Seguro Social', 'Unidad de medicina familiar', 'Urbana', 'Eje Norte 1, Colonia Colorines', '51230']] as [$clues, $name, $loc, $institution, $type, $topology, $address, $cp]) {
                DB::table('unidades_medicas')->updateOrInsert(['clave_clues' => $clues], ['id_jurisdiccion' => $jurisdiction, 'id_localidad' => $locality[$loc], 'id_tipo_unidad' => $types[$type], 'id_tipologia' => $topologies[$topology], 'id_institucion_salud' => $institutions[$institution], 'nom_unidad' => $name, 'domicilio' => $address, 'cp' => $cp, 'horario' => 'Lunes a viernes, 08:00 a 16:00', 'updated_at' => now(), 'created_at' => now()]);
            }
            $this->seedAcademicCatalogs();
        });
    }

    /** @return array<string, int> */
    private function ids(string $table, string $key, array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            DB::table($table)->updateOrInsert(['nombre' => $name], ['updated_at' => now(), 'created_at' => now()]);
            $ids[$name] = DB::table($table)->where('nombre', $name)->value($key);
        }

        return $ids;
    }

    private function seedAcademicCatalogs(): void
    {
        $this->insertNames('carreras', ['Medicina', 'Odontología', 'Enfermería', 'Nutrición', 'Gerontología', 'Atención Médica', 'Regulación Sanitaria']);
        $this->insertNames('generos', ['No especificado']);
        $this->insertNames('nacionalidades', ['Mexicana']);
        $this->insertNames('instituciones', ['Universidad Autónoma del Estado de México', 'Tecnológico de Estudios Superiores de Valle de Bravo']);
        $this->insertNames('nivel_academico', ['Licenciatura', 'Técnico Superior Universitario', 'Técnico']);
        $this->insertNames('tipos_personal', ['Pasante de servicio social', 'Residente', 'Practicante']);
        $this->insertNames('vertientes', ['Servicio social', 'Prácticas profesionales', 'Residencia profesional']);
        $this->insertNames('tipos_campos_clinicos', ['Consulta externa', 'Hospitalización', 'Atención comunitaria']);
        $this->insertNames('fuentes_financiamiento_beca', ['Beca federal', 'Beca estatal', 'Sin beca']);
        foreach ([
            ['nombre' => 'Servicio social de Medicina', 'clave_programa' => 'SS-MED-DEMO'],
            ['nombre' => 'Servicio social de Enfermería', 'clave_programa' => 'SS-ENF-DEMO'],
            ['nombre' => 'Servicio social de Odontología', 'clave_programa' => 'SS-ODO-DEMO'],
        ] as $program) {
            DB::table('programas')->updateOrInsert(['nombre' => $program['nombre']], [...$program, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    private function insertNames(string $table, array $names): void
    {
        foreach ($names as $name) {
            DB::table($table)->updateOrInsert(['nombre' => $name], ['updated_at' => now(), 'created_at' => now()]);
        }
    }
}
