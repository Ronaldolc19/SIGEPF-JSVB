<?php

use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function registrationAdmin(): User
{
    return User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
}

/** @return array<string, mixed> */
function academicPayload(PersonalFormacion $personal): array
{
    return [
        'id_tipo_personal' => $personal->id_tipo_personal,
        'fecha_ingreso' => '2026-01-01', 'fecha_termino' => null,
        'id_institucion' => DB::table('instituciones')->insertGetId(['nombre' => 'Institución de prueba']),
        'id_carrera' => DB::table('carreras')->insertGetId(['nombre' => 'Carrera de prueba']),
        'id_nivel_academico' => DB::table('nivel_academico')->insertGetId(['nombre' => 'Nivel de prueba']),
        'matricula' => 'TEST-001', 'promedio' => '9.5',
    ];
}

function registrationUnit(): int
{
    $municipio = DB::table('municipios')->insertGetId([
        'nom_mun' => 'Municipio de prueba',
        'id_entidad_federativa' => DB::table('entidades_federativas')->insertGetId(['nombre' => 'Entidad '.fake()->uuid()]),
    ]);

    return DB::table('unidades_medicas')->insertGetId([
        'id_localidad' => DB::table('localidades')->insertGetId(['id_municipio' => $municipio, 'nom_loc' => 'Localidad de prueba']),
        'id_jurisdiccion' => DB::table('jurisdicciones')->insertGetId(['nombre' => 'Jurisdicción de prueba']),
        'id_tipo_unidad' => DB::table('tipo_unidades')->insertGetId(['nombre' => 'Tipo de prueba']),
        'id_tipologia' => DB::table('tipologias_unidades')->insertGetId(['nombre' => 'Tipología de prueba']),
        'id_institucion_salud' => DB::table('instituciones_salud')->insertGetId(['nombre' => 'Salud '.fake()->uuid()]),
        'clave_clues' => fake()->unique()->numerify('TEST########'), 'nom_unidad' => 'Unidad de prueba',
        'domicilio' => 'Domicilio de prueba', 'cp' => '00000', 'horario' => '08:00-16:00',
    ]);
}

test('admin completes academics and can correct them without duplicating a participation', function () {
    $personal = PersonalFormacion::factory()->create();
    $payload = academicPayload($personal);
    $this->actingAs(registrationAdmin())->get(route('personal.edit', $personal->id_personal))->assertOk();
    $this->put(route('personal.update', $personal->id_personal), [...$payload, 'id_persona' => 999, 'estado' => 'Liberado'])->assertRedirect(route('personal.show', $personal->id_personal));
    $this->assertDatabaseHas('datos_academicos', ['id_personal' => $personal->id_personal, 'matricula' => 'TEST-001']);
    expect($personal->fresh()->estado)->toBe('En formacion');
    $this->put(route('personal.update', $personal->id_personal), [...$payload, 'matricula' => 'CORREGIDA'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('datos_academicos', 1);
    $this->get(route('personal.show', $personal->id_personal))->assertInertia(fn (Assert $page) => $page
        ->where('academicos.matricula', 'CORREGIDA')->where('academicos.institucion', 'Institución de prueba'));
});

test('invalid dates and inactive academic references leave participation untouched', function () {
    $personal = PersonalFormacion::factory()->create();
    $payload = academicPayload($personal);
    DB::table('carreras')->where('id_carrera', $payload['id_carrera'])->update(['deleted_at' => now()]);
    $this->actingAs(registrationAdmin())->put(route('personal.update', $personal->id_personal),
        [...$payload, 'fecha_termino' => '2025-12-31'])->assertSessionHasErrors(['id_carrera', 'fecha_termino']);
    $this->assertDatabaseCount('datos_academicos', 0);
    expect($personal->fresh()->fecha_ingreso->toDateString())->toBe(today()->toDateString());
});

test('admin records closes and changes units preserving clinical classifications and history', function () {
    $personal = PersonalFormacion::factory()->create(['fecha_ingreso' => '2026-01-01']);
    $unit = registrationUnit();
    $program = DB::table('programas')->insertGetId(['nombre' => 'Programa de prueba', 'clave_programa' => 'PRUEBA']);
    $payload = ['id_unidad' => $unit, 'fecha_inicio' => '2026-01-01', 'id_programa' => $program, 'numero_campo_clinico' => 'EXT-001'];
    $this->actingAs(registrationAdmin())->post(route('adscripciones.store', $personal->id_personal), $payload)->assertSessionHasNoErrors();
    $first = DB::table('adscripciones_personal')->first();
    $this->get(route('personal.show', $personal->id_personal))->assertInertia(fn (Assert $page) => $page
        ->where('adscripciones.0.clave_programa', 'PRUEBA')->where('adscripciones.0.numero_campo_clinico', 'EXT-001')
        ->where('adscripciones.0.municipio', 'Municipio de prueba')->where('adscripciones.0.unidad', 'Unidad de prueba'));
    $this->post(route('adscripciones.store', $personal->id_personal), [...$payload, 'fecha_inicio' => '2026-03-01'])->assertSessionHasErrors('fecha_inicio');
    $this->put(route('adscripciones.update', [$personal->id_personal, $first->id_adscripcion]),
        [...$payload, 'fecha_fin' => '2026-02-28', 'motivo_fin' => 'Cambio de unidad'])->assertSessionHasNoErrors();
    $this->post(route('adscripciones.store', $personal->id_personal),
        ['id_unidad' => registrationUnit(), 'fecha_inicio' => '2026-03-01'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('adscripciones_personal', 2);
    $this->assertDatabaseHas('adscripciones_personal', ['id_adscripcion' => $first->id_adscripcion, 'id_unidad' => $unit, 'motivo_fin' => 'Cambio de unidad']);
});

test('adscription rejects another participation child and invalid period or deleted units', function () {
    $personal = PersonalFormacion::factory()->create(['fecha_ingreso' => '2026-02-01', 'fecha_termino' => '2026-12-31']);
    $other = PersonalFormacion::factory()->create();
    $unit = registrationUnit();
    $id = DB::table('adscripciones_personal')->insertGetId(['id_personal' => $other->id_personal, 'id_unidad' => $unit, 'fecha_inicio' => '2026-01-01']);
    $this->actingAs(registrationAdmin())->get(route('adscripciones.edit', [$personal->id_personal, $id]))->assertNotFound();
    $this->put(route('adscripciones.update', [$personal->id_personal, $id]), ['id_unidad' => $unit, 'fecha_inicio' => '2026-02-01'])->assertNotFound();
    $this->post(route('adscripciones.store', $personal->id_personal), ['id_unidad' => $unit, 'fecha_inicio' => '2026-01-01'])->assertSessionHasErrors('fecha_inicio');
    $this->post(route('adscripciones.store', $personal->id_personal), ['id_unidad' => $unit, 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-02-01'])->assertSessionHasErrors(['fecha_fin', 'motivo_fin']);
    DB::table('unidades_medicas')->where('id_unidad', $unit)->update(['deleted_at' => now()]);
    $this->post(route('adscripciones.store', $personal->id_personal), ['id_unidad' => $unit, 'fecha_inicio' => '2026-03-01'])->assertSessionHasErrors('id_unidad');
    $this->assertDatabaseCount('adscripciones_personal', 1);
});

test('participation dates cannot exclude recorded adscriptions', function () {
    $personal = PersonalFormacion::factory()->create(['fecha_ingreso' => '2026-01-01']);
    DB::table('adscripciones_personal')->insert(['id_personal' => $personal->id_personal, 'id_unidad' => registrationUnit(), 'fecha_inicio' => '2026-01-01']);
    $payload = academicPayload($personal);
    $this->actingAs(registrationAdmin())->put(route('personal.update', $personal->id_personal),
        [...$payload, 'fecha_termino' => '2026-12-31'])->assertSessionHasErrors('fecha_termino');
    $this->assertDatabaseCount('datos_academicos', 0);
});

test('operational roles cannot change academics or adscriptions', function (string $role) {
    $personal = PersonalFormacion::factory()->create();
    $user = User::factory()->has(Rol::factory()->state(['codigo' => $role]), 'roles')->create();
    $this->actingAs($user)->get(route('personal.edit', $personal->id_personal))->assertForbidden();
    $this->put(route('personal.update', $personal->id_personal), [])->assertForbidden();
    $this->get(route('adscripciones.create', $personal->id_personal))->assertForbidden();
    $this->post(route('adscripciones.store', $personal->id_personal), [])->assertForbidden();
    $this->put(route('adscripciones.update', [$personal->id_personal, 1]), [])->assertForbidden();
})->with(['asesor', 'pasante']);

test('guests must authenticate to modify participation', function () {
    $this->put(route('personal.update', 1), [])->assertRedirect(route('login'));
    $this->post(route('adscripciones.store', 1), [])->assertRedirect(route('login'));
});

test('person identification derives residence and age while using the existing gender catalog', function () {
    $personal = PersonalFormacion::factory()->create();
    $gender = DB::table('generos')->insertGetId(['nombre' => 'Dato de prueba']);
    $unit = DB::table('unidades_medicas')->where('id_unidad', registrationUnit())->first();
    $locality = DB::table('localidades')->where('id_localidad', $unit->id_localidad)->first();
    $this->actingAs(registrationAdmin())->put(route('personas.update', $personal->id_persona), [
        'nombre' => 'Persona', 'ap' => 'Prueba', 'fecha_nacimiento' => '2000-01-01',
        'id_genero' => $gender, 'id_municipio' => $locality->id_municipio, 'id_localidad' => $locality->id_localidad,
    ])->assertRedirect();
    $this->assertDatabaseHas('personas', ['id_persona' => $personal->id_persona, 'id_genero' => $gender]);
    $this->get(route('personas.show', $personal->id_persona))->assertInertia(fn (Assert $page) => $page
        ->where('labels.id_genero.label', 'Dato de prueba')
        ->where('edad', Carbon::parse('2000-01-01')->age)
        ->where('residencia.municipio', 'Municipio de prueba')
        ->has('participaciones', 1));
});
