<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductividadDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $examples = [
            'mariana.lopez.demo@example.test' => ['Medicina', [
                ['Consulta general bajo supervisión', [9, 11, 8, 12, 10, 9, 11, 8], 'Participación supervisada en consulta general y orientación sobre seguimiento.'],
                ['Seguimiento de enfermedades crónicas bajo supervisión', [3, 4, 2, 3, 4, 3, 2, 4], 'Apoyo en seguimiento de personas con enfermedades crónicas, bajo supervisión del responsable.'],
                ['Sesión de educación para la salud (Medicina)', [1, 1, 2, 1, 1, 1, 2, 1], 'Sesiones grupales sobre prevención y hábitos saludables; la cantidad corresponde a sesiones.'],
            ]],
            'carlos.martinez.demo@example.test' => ['Enfermería', [
                ['Toma y registro de signos vitales', [16, 20, 18, 22, 17, 19, 21, 18], 'Apoyo en toma y registro de signos vitales durante la atención, bajo supervisión.'],
                ['Curación simple bajo supervisión', [3, 4, 2, 5, 3, 4, 3, 2], 'Apoyo supervisado en curaciones simples; la cantidad corresponde a procedimientos.'],
                ['Sesión de orientación en autocuidado (Enfermería)', [1, 2, 1, 1, 2, 1, 1, 2], 'Sesiones sobre higiene de manos, autocuidado y prevención; se cuentan sesiones, no asistentes.'],
            ]],
            'paola.hernandez.demo@example.test' => ['Odontología', [
                ['Valoración bucodental bajo supervisión', [7, 8, 6, 9, 7, 8, 6, 7], 'Participación supervisada en valoraciones y orientación preventiva de salud bucal.'],
                ['Aplicación preventiva de flúor bajo supervisión', [4, 5, 3, 6, 4, 3, 5, 4], 'Apoyo supervisado en aplicaciones preventivas; se cuentan procedimientos realizados.'],
                ['Sesión de técnica de cepillado', [1, 2, 1, 2, 1, 1, 2, 1], 'Demostraciones educativas de técnica de cepillado; la cantidad corresponde a sesiones.'],
            ]],
        ];
        $inserted = 0;
        $participants = 0;
        DB::transaction(function () use ($examples, &$inserted, &$participants): void {
            foreach ($examples as $email => [$careerName, $activities]) {
                $person = DB::table('personal_formacion as pf')
                    ->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')
                    ->join('datos_academicos as da', 'da.id_personal', '=', 'pf.id_personal')
                    ->join('carreras as c', 'c.id_carrera', '=', 'da.id_carrera')
                    ->where('p.correo', $email)->where('c.nombre', $careerName)->where('pf.estado', 'En formacion')
                    ->whereNull('pf.deleted_at')->whereNull('p.deleted_at')->whereNull('da.deleted_at')->whereNull('c.deleted_at')
                    ->select('pf.id_personal', 'pf.fecha_ingreso', 'pf.fecha_termino', 'c.id_carrera')
                    ->orderByDesc('pf.fecha_ingreso')->lockForUpdate()->first();
                if (! $person) {
                    $this->command?->warn('Sin participación de demostración disponible para '.$careerName.'. No se modificaron otras personas.');

                    continue;
                }
                $participants++;
                foreach ($activities as [$name, $quantities, $description]) {
                    $type = DB::table('tipos_consultas')->where('nombre', $name)->where('id_carrera', $person->id_carrera)->first();
                    if ($type?->deleted_at) {
                        continue;
                    }
                    $typeId = $type?->id_tipo_consulta ?? DB::table('tipos_consultas')->insertGetId([
                        'nombre' => $name, 'id_carrera' => $person->id_carrera, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-09', '2026-11-10', '2026-11-11'] as $index => $date) {
                        if (! $person->fecha_ingreso || $date < $person->fecha_ingreso || ($person->fecha_termino && $date > $person->fecha_termino) || $date > today()->toDateString()) {
                            continue;
                        }
                        $key = ['id_personal' => $person->id_personal, 'id_tipo_consulta' => $typeId, 'fecha_actividad' => $date];
                        if (DB::table('productividad')->where($key)->exists()) {
                            continue;
                        }
                        $registered = CarbonImmutable::parse($date)->startOfDay();
                        DB::table('productividad')->insert($key + [
                            'num_actividades' => $quantities[$index],
                            'descripcion' => 'Ejemplo ficticio para documentación. '.$description,
                            'fecha_registro' => $registered, 'estado' => 'Registrada', 'revision' => 0,
                            'created_at' => $registered, 'updated_at' => $registered,
                        ]);
                        $inserted++;
                    }
                }
            }
        });
        $this->command?->info('Participaciones de demostración: '.$participants.'. Registros nuevos: '.$inserted.'. Periodo: 2 al 11 de noviembre de 2026.');
    }
}
