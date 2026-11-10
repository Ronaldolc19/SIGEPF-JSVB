<?php

use App\Models\Persona;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
});

function completeRegistrationPayload(): array
{
    $entity = DB::table('entidades_federativas')->insertGetId(['nombre' => 'Entidad prueba']);
    $municipality = DB::table('municipios')->insertGetId(['nom_mun' => 'Municipio prueba', 'id_entidad_federativa' => $entity]);
    $locality = DB::table('localidades')->insertGetId(['nom_loc' => 'Localidad prueba', 'id_municipio' => $municipality]);
    $unit = DB::table('unidades_medicas')->insertGetId([
        'id_localidad' => $locality,
        'id_jurisdiccion' => DB::table('jurisdicciones')->insertGetId(['nombre' => 'Jurisdicción prueba']),
        'id_tipo_unidad' => DB::table('tipo_unidades')->insertGetId(['nombre' => 'Tipo prueba']),
        'id_tipologia' => DB::table('tipologias_unidades')->insertGetId(['nombre' => 'Tipología prueba']),
        'id_institucion_salud' => DB::table('instituciones_salud')->insertGetId(['nombre' => 'Salud prueba']),
        'clave_clues' => 'TEST0001', 'nom_unidad' => 'Unidad prueba', 'domicilio' => 'Prueba', 'cp' => '00000', 'horario' => '08:00',
    ]);
    $data = ['nombre' => 'Persona ficticia', 'ap' => 'Prueba', 'curp' => 'XXXX000101MXXXXX00', 'edad_constancia' => 24,
        'id_entidad_federativa' => $entity, 'id_unidad' => $unit,
        'fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2027-07-31',
        'fecha_inicio_adscripcion' => '2026-08-01', 'fecha_fin_adscripcion' => '2027-07-31', 'numero_campo_clinico' => '507'];
    foreach (['id_genero' => 'generos', 'id_nacionalidad' => 'nacionalidades', 'id_tipo_personal' => 'tipos_personal', 'id_institucion' => 'instituciones', 'id_carrera' => 'carreras', 'id_vertiente' => 'vertientes', 'id_tipo_campo_clinico' => 'tipos_campos_clinicos', 'id_fuente_financiamiento_beca' => 'fuentes_financiamiento_beca'] as $key => $table) {
        $data[$key] = DB::table($table)->insertGetId(['nombre' => 'Prueba']);
    }
    $data['id_programa'] = DB::table('programas')->insertGetId(['nombre' => 'Programa prueba', 'clave_programa' => 'TEST']);
    $data['id_promocion'] = DB::table('promociones')->insertGetId(['nombre' => 'Agosto 2026', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-07-31', 'estado' => 'En curso', 'permite_inscripciones' => true]);
    DB::table('promocion_carrera')->insert(['id_promocion' => $data['id_promocion'], 'id_carrera' => $data['id_carrera']]);

    return $data;
}

test('promotion restrictions and individual extensions retain historical records', function () {
    $data = completeRegistrationPayload();
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $other = DB::table('carreras')->insertGetId(['nombre' => 'Otra carrera']);
    $this->actingAs($admin)->post(route('personal.store'), [...$data, 'id_carrera' => $other])->assertSessionHasErrors('id_carrera');
    $this->post(route('personal.store'), [...$data, 'id_promocion' => null])->assertSessionHasErrors('id_promocion');
    DB::table('promociones')->where('id_promocion', $data['id_promocion'])->update(['permite_inscripciones' => false]);
    $this->post(route('personal.store'), $data)->assertSessionHasErrors('id_promocion');
    DB::table('promociones')->where('id_promocion', $data['id_promocion'])->update(['permite_inscripciones' => true]);
    $this->post(route('personal.store'), $data)->assertSessionHasNoErrors();
    $personal = DB::table('personal_formacion')->first();
    $ads = DB::table('adscripciones_personal')->first();
    DB::table('promociones')->where('id_promocion', $data['id_promocion'])->update(['estado' => 'Cerrada', 'permite_inscripciones' => false]);
    $extended = [...$data, 'id_adscripcion' => $ads->id_adscripcion, 'fecha_termino' => '2027-09-30', 'fecha_fin_adscripcion' => '2027-09-30'];
    $this->put(route('personal.registro.update', $personal->id_personal), $extended)->assertSessionHasErrors('motivo_periodo');
    $this->put(route('personal.registro.update', $personal->id_personal), [...$extended, 'motivo_periodo' => 'Prórroga autorizada para completar el servicio.'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('personal_formacion', ['id_personal' => $personal->id_personal, 'fecha_termino' => '2027-09-30', 'id_promocion' => $data['id_promocion'], 'estado' => 'En formacion']);
    $this->assertDatabaseHas('promociones', ['id_promocion' => $data['id_promocion'], 'fecha_fin' => '2027-07-31']);
    $this->assertDatabaseHas('historial_periodos_personal', ['id_personal' => $personal->id_personal, 'termino_anterior' => '2027-07-31', 'fecha_termino' => '2027-09-30', 'id_usuario' => $admin->id]);
    $this->get(route('personal.show', $personal->id_personal))->assertOk();
    $this->put(route('personal.registro.update', $personal->id_personal), [...$extended, 'id_promocion' => null])->assertSessionHasErrors('id_promocion');
});

test('legacy participation can be linked to closed promotion without losing its identity', function () {
    $data = completeRegistrationPayload();
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $this->actingAs($admin)->post(route('personal.store'), $data)->assertSessionHasNoErrors();
    $personal = DB::table('personal_formacion')->first();
    DB::table('personal_formacion')->where('id_personal', $personal->id_personal)->update(['id_promocion' => null]);
    DB::table('promociones')->where('id_promocion', $data['id_promocion'])->update(['estado' => 'Cerrada', 'permite_inscripciones' => false]);
    $this->get(route('personal.registro.edit', $personal->id_personal))->assertOk();
    $this->put(route('personal.registro.update', $personal->id_personal), [...$data, 'id_adscripcion' => DB::table('adscripciones_personal')->value('id_adscripcion')])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('personal_formacion', 1);
    $this->assertDatabaseHas('personal_formacion', ['id_personal' => $personal->id_personal, 'id_promocion' => $data['id_promocion']]);
});

test('guided registration saves all sections together and edits without duplicates', function () {
    $data = completeRegistrationPayload();
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $this->actingAs($admin)->post(route('personal.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $personal = DB::table('personal_formacion')->first();
    $adscription = DB::table('adscripciones_personal')->first();
    $this->assertDatabaseHas('personal_formacion', ['edad_constancia' => 24]);
    $this->assertDatabaseHas('datos_academicos', ['matricula' => null, 'id_nivel_academico' => null]);
    $this->get(route('personal.registro.edit', $personal->id_personal))->assertOk();
    $this->getJson(route('personal.unidad', $data['id_unidad']))->assertOk()->assertJsonPath('municipio', 'Municipio prueba');
    $this->put(route('personal.registro.update', $personal->id_personal), [...$data, 'id_adscripcion' => $adscription->id_adscripcion, 'nombre' => 'Corregida'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('personas', 1);
    $this->assertDatabaseCount('datos_academicos', 1);
    $this->assertDatabaseCount('adscripciones_personal', 1);
    $this->assertDatabaseHas('personas', ['nombre' => 'Corregida']);
});

test('late registration conflicts roll back every section', function () {
    $data = completeRegistrationPayload();
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $this->actingAs($admin)->post(route('personal.store'), [...$data, 'id_adscripcion' => 999])->assertSessionHasErrors('id_adscripcion');
    foreach (['personas', 'personal_formacion', 'datos_academicos', 'adscripciones_personal'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

test('registration reuses a person and links an account without granting roles', function () {
    $data = completeRegistrationPayload();
    $persona = Persona::factory()->create(['curp' => $data['curp']]);
    $account = User::factory()->create();
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $this->actingAs($admin)->post(route('personal.store'), [...$data, 'id_persona' => $persona->id_persona, 'id_usuario' => $account->id])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('personas', 1);
    expect($account->fresh()->id_persona)->toBe($persona->id_persona);
    expect($account->roles()->count())->toBe(0);
    $this->post(route('personal.store'), $data)->assertSessionHasErrors('curp');
    $this->assertDatabaseCount('personal_formacion', 1);
});

test('invalid catalog and dates prevent registration', function () {
    $data = completeRegistrationPayload();
    DB::table('carreras')->where('id_carrera', $data['id_carrera'])->update(['deleted_at' => now()]);
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $this->actingAs($admin)->post(route('personal.store'), [...$data, 'fecha_fin_adscripcion' => '2028-01-01'])->assertSessionHasErrors(['id_carrera', 'fecha_fin_adscripcion']);
    $this->assertDatabaseCount('personas', 0);
});

test('operational roles cannot use guided registration', function (string $role) {
    $user = User::factory()->has(Rol::factory()->state(['codigo' => $role]), 'roles')->create();
    $this->actingAs($user)->get(route('personal.create'))->assertForbidden();
    $this->post(route('personal.store'), [])->assertForbidden();
    $this->put(route('personal.registro.update', 1), [])->assertForbidden();
})->with(['asesor', 'pasante']);

test('catalog options can be created without leaving registration', function () {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $this->actingAs($admin)->postJson(route('catalogos.store', 'instituciones'), ['nombre' => 'Institución prueba', 'clave_institucion' => '1502'])->assertCreated()->assertJsonPath('option.context', '1502');
    $this->assertDatabaseHas('instituciones', ['clave_institucion' => '1502']);
});
