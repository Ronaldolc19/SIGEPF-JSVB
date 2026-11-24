<?php

use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\ProductividadAccessSeeder;
use Database\Seeders\ProductividadCatalogosSeeder;
use Database\Seeders\ProductividadFlujoDemoSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
    $this->seed([AccessSeeder::class, ProductividadAccessSeeder::class, ProductividadCatalogosSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->roles()->attach(Rol::where('codigo', 'administrador')->first());
    $promotion = DB::table('promociones')->insertGetId(['nombre' => 'Primera', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-10-31', 'estado' => 'Cerrada']);
    $career = DB::table('carreras')->insertGetId(['nombre' => 'Enfermería']);
    DB::table('promocion_carrera')->insert(['id_promocion' => $promotion, 'id_carrera' => $career]);
    $locality = DB::table('localidades')->insertGetId(['nom_loc' => 'Localidad', 'id_municipio' => DB::table('municipios')->insertGetId(['nom_mun' => 'Municipio'])]);
    $unit = DB::table('unidades_medicas')->insertGetId([
        'id_localidad' => $locality, 'id_jurisdiccion' => DB::table('jurisdicciones')->insertGetId(['nombre' => 'Jurisdicción']),
        'id_tipo_unidad' => DB::table('tipo_unidades')->insertGetId(['nombre' => 'Centro de salud']),
        'id_tipologia' => DB::table('tipologias_unidades')->insertGetId(['nombre' => 'Urbana']),
        'clave_clues' => 'TEST0001', 'nom_unidad' => 'Unidad de prueba', 'domicilio' => 'Prueba', 'cp' => '00000', 'horario' => '08:00',
    ]);
    $this->person = PersonalFormacion::factory()->create(['id_promocion' => $promotion, 'fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2026-12-31']);
    $this->trainee = User::factory()->create(['id_persona' => $this->person->id_persona]);
    $this->trainee->roles()->attach(Rol::where('codigo', 'pasante')->first());
    DB::table('datos_academicos')->insert(['id_personal' => $this->person->id_personal, 'id_carrera' => $career, 'id_institucion' => DB::table('instituciones')->insertGetId(['nombre' => 'Universidad'])]);
    DB::table('adscripciones_personal')->insert(['id_personal' => $this->person->id_personal, 'id_unidad' => $unit, 'fecha_inicio' => '2026-08-01']);
    $this->plan = [
        'id_promocion' => $promotion, 'id_carrera' => $career, 'id_unidad' => $unit,
        'id_tipo_consulta' => DB::table('tipos_consultas')->insertGetId(['nombre' => 'Signos vitales', 'id_carrera' => $career]),
        'id_categoria_productividad' => DB::table('categorias_productividad')->value('id_categoria_productividad'),
        'id_unidad_medida' => DB::table('unidades_medida_productividad')->value('id_unidad_medida'),
        'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31', 'meta_unidad' => 100,
    ];
    $this->capture = ['id_personal' => $this->person->id_personal, 'id_tipo_consulta' => $this->plan['id_tipo_consulta'], 'fecha_actividad' => '2026-11-10', 'num_actividades' => 5];
});

function createProductivityProgram(): int
{
    test()->actingAs(test()->admin)->post(route('productividad.programacion.store'), test()->plan)->assertSessionHasNoErrors();

    return DB::table('programacion_productividad')->max('id_programacion');
}

test('demonstration flow adds coherent programming and captures without overwriting existing data', function () {
    $this->travelTo(now()->setDate(2026, 11, 23)->setTime(20, 0));
    $this->person->update(['id_promocion' => null]);
    $this->person->persona->update(['correo' => 'carlos.martinez.demo@example.test']);
    $this->seed(ProductividadFlujoDemoSeeder::class);
    $this->assertDatabaseCount('programacion_productividad', 3);
    $this->assertDatabaseCount('metas_productividad_personal', 3);
    $this->assertDatabaseCount('productividad', 15);
    expect(DB::table('productividad')->where('estado', 'Validada')->count())->toBe(9);
    expect(DB::table('productividad')->where('estado', 'Por corregir')->count())->toBe(1);
    $capture = DB::table('productividad')->where('estado', 'Registrada')->first();
    DB::table('productividad')->where('id_productividad', $capture->id_productividad)->update(['descripcion' => 'Corrección manual conservada']);
    $this->seed(ProductividadFlujoDemoSeeder::class);
    $this->assertDatabaseCount('productividad', 15);
    $this->assertDatabaseHas('productividad', ['id_productividad' => $capture->id_productividad, 'descripcion' => 'Corrección manual conservada']);
    $this->actingAs($this->trainee)->get(route('productividad.report', $this->person->id_personal))->assertOk()->assertInertia(fn (Assert $page) => $page->has('filas', 3));
});

test('administrator creates different programming for successive promotions without changing the previous one', function () {
    $first = createProductivityProgram();
    $this->get(route('productividad.programacion.index', ['promocion' => $this->plan['id_promocion']]))->assertInertia(fn (Assert $p) => $p->has('programaciones.data', 1));
    $next = DB::table('promociones')->insertGetId(['nombre' => 'Siguiente', 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-12-31', 'estado' => 'Programada']);
    DB::table('promocion_carrera')->insert(['id_promocion' => $next, 'id_carrera' => $this->plan['id_carrera']]);
    $this->post(route('productividad.programacion.store'), array_replace($this->plan, ['id_promocion' => $next, 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-12-31', 'meta_unidad' => 200]))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('programacion_productividad', ['id_programacion' => $first, 'meta_unidad' => 100]);
    $this->get(route('productividad.programacion.index', ['promocion' => $next]))->assertInertia(fn (Assert $p) => $p->has('programaciones.data', 1)->where('programaciones.data.0.meta_unidad', 200));
    $this->get(route('productividad.programacion.create'))->assertOk();
    $this->get(route('productividad.programacion.edit', $first))->assertOk();
    $this->get(route('productividad.programacion.index', ['promocion' => 'vigentes']))->assertOk();
    $this->get(route('productividad.programacion.index', ['promocion' => 'prorrogas']))->assertOk();
});

test('programming rejects overlap invalid careers and stale edits', function () {
    $id = createProductivityProgram();
    $this->post(route('productividad.programacion.store'), $this->plan)->assertSessionHasErrors('fecha_inicio');
    $career = DB::table('carreras')->insertGetId(['nombre' => 'Medicina']);
    $this->post(route('productividad.programacion.store'), array_replace($this->plan, ['id_carrera' => $career]))->assertSessionHasErrors('id_carrera');
    $this->put(route('productividad.programacion.update', $id), $this->plan + ['revision' => 8])->assertSessionHasErrors('revision');
    $this->put(route('productividad.programacion.update', $id), array_replace($this->plan, ['meta_unidad' => 90, 'revision' => 0]))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('programacion_productividad', ['id_programacion' => $id, 'meta_unidad' => 90, 'revision' => 1]);
});

test('capture requires the matching promotion career unit and period including individual extensions', function () {
    $id = createProductivityProgram();
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->capture)->assertSessionHasErrors('id_programacion');
    $this->post(route('productividad.store'), $this->capture + ['id_programacion' => $id])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('productividad', ['id_programacion' => $id, 'num_actividades' => 5]);
    $this->get(route('productividad.create'))->assertInertia(fn (Assert $p) => $p->has('programaciones', 1));
    DB::table('adscripciones_personal')->where('id_personal', $this->person->id_personal)->update(['fecha_fin' => '2026-11-08']);
    $this->post(route('productividad.store'), $this->capture + ['id_programacion' => $id])->assertSessionHasErrors('id_programacion');
    $this->get(route('productividad.report', $this->person->id_personal))->assertInertia(fn (Assert $p) => $p->has('filas', 1)->where('filas.0.realizadas', 5));
});

test('activities from another promotion or an unprogrammed date cannot be submitted', function () {
    $this->plan['fecha_inicio'] = '2026-11-10';
    $id = createProductivityProgram();
    $this->actingAs($this->trainee)->post(route('productividad.store'), array_replace($this->capture, ['id_programacion' => $id, 'fecha_actividad' => '2026-11-09']))->assertSessionHasErrors('id_programacion');
    $other = DB::table('promociones')->insertGetId(['nombre' => 'Otra promoción', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-07-31']);
    DB::table('promocion_carrera')->insert(['id_promocion' => $other, 'id_carrera' => $this->plan['id_carrera']]);
    $this->actingAs($this->admin)->post(route('productividad.programacion.store'), array_replace($this->plan, ['id_promocion' => $other]))->assertSessionHasNoErrors();
    $otherProgram = DB::table('programacion_productividad')->max('id_programacion');
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->capture + ['id_programacion' => $otherProgram])->assertSessionHasErrors('id_programacion');
    $this->get(route('productividad.create'))->assertInertia(fn (Assert $p) => $p->has('programaciones', 1)->where('programaciones.0.id_programacion', $id));
    $this->assertDatabaseCount('productividad', 0);
});

test('captured definitions and goals are frozen and catalog renaming does not rewrite history', function () {
    $id = createProductivityProgram();
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->capture + ['id_programacion' => $id])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->put(route('productividad.programacion.update', $id), array_replace($this->plan, ['meta_unidad' => 500, 'revision' => 0]))->assertSessionHasErrors('revision');
    DB::table('tipos_consultas')->where('id_tipo_consulta', $this->plan['id_tipo_consulta'])->update(['nombre' => 'Nombre nuevo']);
    $this->actingAs($this->trainee)->get(route('productividad.index'))->assertInertia(fn (Assert $p) => $p->where('registros.data.0.actividad', 'Signos vitales'));
    $this->get(route('productividad.report', $this->person->id_personal))->assertInertia(fn (Assert $p) => $p->where('filas.0.actividad', 'Signos vitales')->where('filas.0.meta_unidad', 100));
});

test('zero no activity and not applicable are explicit and require context', function () {
    $id = createProductivityProgram();
    $this->actingAs($this->trainee);
    foreach (['Sin actividad', 'No aplica'] as $result) {
        $payload = array_replace($this->capture, ['id_programacion' => $id, 'resultado' => $result, 'num_actividades' => 0, 'fecha_actividad' => $result === 'No aplica' ? '2026-11-11' : '2026-11-10']);
        $this->post(route('productividad.store'), $payload)->assertSessionHasErrors('descripcion');
        $this->post(route('productividad.store'), $payload + ['descripcion' => 'Sin insumos disponibles para esta fecha.'])->assertSessionHasNoErrors();
    }
    $this->get(route('productividad.report', $this->person->id_personal))->assertInertia(fn (Assert $p) => $p->where('filas.0.realizadas', 0)->where('filas.0.registros', 2)->where('filas.0.no_aplica', 1)->where('filas.0.sin_actividad', 1));
});

test('individual targets and institutional contributions are separate and use validated quantities only', function () {
    $id = createProductivityProgram();
    $this->post(route('productividad.programacion.goal', $id), ['id_personal' => $this->person->id_personal, 'cantidad' => 10])->assertSessionHasNoErrors();
    $this->post(route('productividad.programacion.goal', $id), ['id_personal' => $this->person->id_personal, 'cantidad' => 20])->assertSessionHasErrors('cantidad');
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->capture + ['id_programacion' => $id])->assertSessionHasNoErrors();
    $record = DB::table('productividad')->value('id_productividad');
    $this->get(route('productividad.report', $this->person->id_personal))->assertInertia(fn (Assert $p) => $p->where('filas.0.realizadas', 5)->where('filas.0.validadas', 0)->where('filas.0.meta_individual', 10));
    $this->actingAs($this->admin)->post(route('productividad.review', $record), ['estado' => 'Validada', 'revision' => 0])->assertSessionHasNoErrors();
    $this->get(route('productividad.report', $this->person->id_personal))->assertInertia(fn (Assert $p) => $p->where('filas.0.validadas', 5)->where('filas.0.aportacion_unidad', 5)->where('filas.0.avance_individual', 50));
});

test('zero targets and missing captures do not cause division by zero or imply activity occurred', function () {
    $this->plan['meta_unidad'] = 0;
    $id = createProductivityProgram();
    $this->post(route('productividad.programacion.goal', $id), ['id_personal' => $this->person->id_personal, 'cantidad' => 0])->assertSessionHasNoErrors();
    $this->actingAs($this->trainee)->get(route('productividad.report', $this->person->id_personal))->assertInertia(fn (Assert $p) => $p->where('filas.0.registros', 0)->where('filas.0.aportacion_unidad', null)->where('filas.0.avance_individual', null));
});

test('disabled programming stops new captures but retains reports and permits correction of existing entries', function () {
    $id = createProductivityProgram();
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->capture + ['id_programacion' => $id])->assertSessionHasNoErrors();
    $record = DB::table('productividad')->value('id_productividad');
    $this->actingAs($this->admin)->post(route('productividad.programacion.toggle', $id), ['activa' => false, 'revision' => 0])->assertSessionHasNoErrors();
    $this->post(route('productividad.programacion.toggle', $id), ['activa' => true, 'revision' => 0])->assertSessionHasErrors('revision');
    $this->actingAs($this->trainee)->post(route('productividad.store'), array_replace($this->capture, ['id_programacion' => $id, 'fecha_actividad' => '2026-11-11']))->assertSessionHasErrors('id_programacion');
    $this->put(route('productividad.update', $record), array_replace($this->capture, ['id_programacion' => $id, 'revision' => 0, 'num_actividades' => 4]))->assertSessionHasNoErrors();
    $this->get(route('productividad.report', $this->person->id_personal))->assertInertia(fn (Assert $p) => $p->where('filas.0.realizadas', 4));
});

test('trainees cannot administer programming or read other trainees reports', function () {
    $id = createProductivityProgram();
    $other = PersonalFormacion::factory()->create();
    $this->actingAs($this->trainee)->get(route('productividad.programacion.index'))->assertForbidden();
    $this->post(route('productividad.programacion.store'), $this->plan)->assertForbidden();
    $this->post(route('productividad.programacion.toggle', $id), ['activa' => false, 'revision' => 0])->assertForbidden();
    $this->post(route('productividad.programacion.goal', $id), ['id_personal' => $this->person->id_personal, 'cantidad' => 20])->assertForbidden();
    $this->get(route('productividad.report', $other->id_personal))->assertNotFound();
    $this->actingAs($this->admin)->post(route('productividad.programacion.goal', $id), ['id_personal' => $other->id_personal, 'cantidad' => 20])->assertSessionHasErrors('id_personal');
});
