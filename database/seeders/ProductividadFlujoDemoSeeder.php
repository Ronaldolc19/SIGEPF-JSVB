<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductividadFlujoDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reviewer = User::whereHas('roles', fn (Builder $q): Builder => $q->where('codigo', 'administrador'))->firstOrFail();
        $this->call(ProductividadCatalogosSeeder::class);
        $examples = [
            'mariana.lopez.demo@example.test' => ['Medicina', [
                ['Consulta general supervisada', 'Consultas', 10, 120],
                ['Seguimiento supervisado de enfermedades crónicas', 'Consultas', 4, 50],
                ['Educación para la salud', 'Sesiones', 1, 10],
            ]],
            'carlos.martinez.demo@example.test' => ['Enfermería', [
                ['Toma y registro de signos vitales', 'Registros', 18, 220],
                ['Curaciones simples supervisadas', 'Procedimientos', 4, 50],
                ['Orientación en higiene de manos y autocuidado', 'Sesiones', 1, 10],
            ]],
            'paola.hernandez.demo@example.test' => ['Odontología', [
                ['Valoración bucodental supervisada', 'Consultas', 8, 100],
                ['Aplicación preventiva de flúor supervisada', 'Procedimientos', 5, 60],
                ['Demostración de técnica de cepillado', 'Sesiones', 1, 10],
            ]],
        ];
        $inserted = 0;
        DB::transaction(function () use ($reviewer, $examples, &$inserted): void {
            $promotionName = 'DEMO · Servicio social agosto 2026 – julio 2027';
            $promotion = DB::table('promociones')->where('nombre', $promotionName)->first();
            if ($promotion?->deleted_at) {
                throw new \RuntimeException('La promoción de demostración fue eliminada; no se restaurará automáticamente.');
            }
            $promotionId = $promotion?->id_promocion ?? DB::table('promociones')->insertGetId([
                'nombre' => $promotionName, 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-07-31',
                'estado' => 'En curso', 'permite_inscripciones' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($examples as $email => [$career, $activities]) {
                $person = DB::table('personal_formacion as pf')->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')
                    ->join('datos_academicos as da', 'da.id_personal', '=', 'pf.id_personal')->join('carreras as c', 'c.id_carrera', '=', 'da.id_carrera')
                    ->where('p.correo', $email)->where('c.nombre', $career)->where('pf.estado', 'En formacion')
                    ->whereNull('p.deleted_at')->whereNull('pf.deleted_at')->whereNull('da.deleted_at')->whereNull('c.deleted_at')
                    ->select('pf.*', 'c.id_carrera')->orderByDesc('pf.fecha_ingreso')->lockForUpdate()->first();
                if (! $person || ($person->id_promocion && $person->id_promocion !== $promotionId)) {
                    $this->command?->warn('Se omitió '.$career.': no existe ejemplo disponible sin otra promoción.');

                    continue;
                }
                $assignment = DB::table('adscripciones_personal')->where('id_personal', $person->id_personal)->whereNull('deleted_at')
                    ->where('fecha_inicio', '<=', '2026-11-17')->where(fn (\Illuminate\Database\Query\Builder $q): \Illuminate\Database\Query\Builder => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', '2026-11-30'))->first();
                if (! $assignment || $person->fecha_ingreso > '2026-11-17' || ($person->fecha_termino && $person->fecha_termino < '2026-11-30')) {
                    $this->command?->warn('Se omitió '.$career.': periodo o adscripción incompatible.');

                    continue;
                }
                DB::table('personal_formacion')->where('id_personal', $person->id_personal)->whereNull('id_promocion')->update(['id_promocion' => $promotionId, 'updated_at' => now()]);
                DB::table('promocion_carrera')->insertOrIgnore(['id_promocion' => $promotionId, 'id_carrera' => $person->id_carrera]);
                foreach ($activities as $activityIndex => [$name, $measure, $quantity, $goal]) {
                    $name .= ' (demostración)';
                    $type = DB::table('tipos_consultas')->where('nombre', $name)->where('id_carrera', $person->id_carrera)->first();
                    if ($type?->deleted_at) {
                        continue;
                    }
                    $typeId = $type?->id_tipo_consulta ?? DB::table('tipos_consultas')->insertGetId(['nombre' => $name, 'id_carrera' => $person->id_carrera, 'created_at' => now(), 'updated_at' => now()]);
                    $categoryName = $activityIndex === 2 ? 'Promoción de la salud' : 'Atención clínica';
                    $categoryId = DB::table('categorias_productividad')->where('nombre', $categoryName)->whereNull('deleted_at')->value('id_categoria_productividad');
                    $measureId = DB::table('unidades_medida_productividad')->where('nombre', $measure)->whereNull('deleted_at')->value('id_unidad_medida');
                    if (! $categoryId || ! $measureId) {
                        throw new \RuntimeException('Falta una categoría o unidad de medida vigente para la demostración.');
                    }
                    $key = ['id_promocion' => $promotionId, 'id_carrera' => $person->id_carrera, 'id_unidad' => $assignment->id_unidad, 'id_tipo_consulta' => $typeId, 'fecha_inicio' => '2026-11-17', 'fecha_fin' => '2026-11-30'];
                    $program = DB::table('programacion_productividad')->where($key)->first();
                    if ($program && ! $program->activa) {
                        continue;
                    }
                    $programId = $program?->id_programacion ?? DB::table('programacion_productividad')->insertGetId($key + [
                        'id_categoria_productividad' => $categoryId, 'id_unidad_medida' => $measureId, 'actividad' => $name,
                        'categoria' => $categoryName, 'unidad_medida' => $measure, 'meta_unidad' => $goal * 4, 'activa' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('metas_productividad_personal')->insertOrIgnore(['id_programacion' => $programId, 'id_personal' => $person->id_personal, 'cantidad' => $goal, 'created_at' => now(), 'updated_at' => now()]);
                    foreach (['2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-23'] as $day => $date) {
                        $captureKey = ['id_personal' => $person->id_personal, 'id_tipo_consulta' => $typeId, 'fecha_actividad' => $date];
                        if ($date > now('America/Mexico_City')->toDateString() || DB::table('productividad')->where($captureKey)->exists()) {
                            continue;
                        }
                        $zero = $day === 3 && $activityIndex === 2;
                        $state = $day < 3 ? 'Validada' : ($day === 3 && $activityIndex === 0 ? 'Por corregir' : 'Registrada');
                        DB::table('productividad')->insert($captureKey + [
                            'id_programacion' => $programId, 'actividad_snapshot' => $name, 'num_actividades' => $zero ? 0 : $quantity + ($day % 2),
                            'resultado' => $zero ? 'Sin actividad' : 'Realizada', 'estado' => $state,
                            'descripcion' => 'Ejemplo ficticio para documentación. '.($zero ? 'La sesión educativa se reprogramó por disponibilidad del grupo.' : 'Actividad supervisada de '.$career.'. Cantidad expresada en '.mb_strtolower($measure).'.'),
                            'observaciones' => $state === 'Por corregir' ? 'Revisión simulada: precisar el turno y verificar la cantidad capturada.' : ($state === 'Validada' ? 'Revisión simulada para mostrar el avance de metas.' : null),
                            'revisado_por' => $state === 'Registrada' ? null : $reviewer->id, 'fecha_revision' => $state === 'Registrada' ? null : $date.' 18:00:00',
                            'fecha_registro' => $date, 'revision' => $state === 'Registrada' ? 0 : 1, 'created_at' => $date.' 12:00:00', 'updated_at' => $date.' 18:00:00',
                        ]);
                        $inserted++;
                    }
                }
            }
        });
        $this->command?->info('Flujo ficticio preparado. Capturas nuevas: '.$inserted.'. Semanas del 16 y 23 de noviembre de 2026.');
    }
}
