<?php

use App\Models\Asesor;
use App\Models\AsignacionAsesor;
use App\Models\Permiso;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function incidenceAccount(string $code, ?int $person = null): User
{
    $role = Rol::firstOrCreate(['codigo' => $code], ['nombre' => ucfirst($code)]);
    foreach (['ver', 'registrar', 'editar', 'revisar', 'eliminar'] as $action) {
        $permission = Permiso::firstOrCreate(['codigo' => 'incidencias.'.$action], ['nombre' => $action]);
        $role->permisos()->syncWithoutDetaching([$permission->id]);
    }

    return User::factory()->hasAttached($role, [], 'roles')->create(['id_persona' => $person]);
}

function incidencePayload(): array
{
    $personal = PersonalFormacion::factory()->create(['fecha_ingreso' => '2026-01-01', 'fecha_termino' => '2026-12-31']);
    $entity = DB::table('entidades_federativas')->insertGetId(['nombre' => fake()->unique()->uuid()]);
    $municipality = DB::table('municipios')->insertGetId(['nom_mun' => fake()->unique()->uuid(), 'id_entidad_federativa' => $entity]);
    $locality = DB::table('localidades')->insertGetId(['nom_loc' => 'Localidad prueba', 'id_municipio' => $municipality]);
    $unit = DB::table('unidades_medicas')->insertGetId([
        'id_localidad' => $locality,
        'id_jurisdiccion' => DB::table('jurisdicciones')->insertGetId(['nombre' => fake()->unique()->uuid()]),
        'id_tipo_unidad' => DB::table('tipo_unidades')->insertGetId(['nombre' => fake()->unique()->uuid()]),
        'id_tipologia' => DB::table('tipologias_unidades')->insertGetId(['nombre' => fake()->unique()->uuid()]),
        'id_institucion_salud' => DB::table('instituciones_salud')->insertGetId(['nombre' => fake()->unique()->uuid()]),
        'clave_clues' => fake()->unique()->numerify('TEST########'), 'nom_unidad' => 'Unidad prueba', 'domicilio' => 'Prueba', 'cp' => '00000', 'horario' => '08:00',
    ]);
    DB::table('adscripciones_personal')->insert(['id_personal' => $personal->id_personal, 'id_unidad' => $unit, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']);
    $category = DB::table('categorias')->insertGetId(['nombre' => fake()->unique()->uuid()]);
    $type = DB::table('tipos_incidencias')->insertGetId(['nombre' => 'Permiso personal', 'id_categoria' => $category]);

    return ['id_personal' => $personal->id_personal, 'id_tipo_incidencia' => $type, 'fecha_hecho' => '2026-11-05', 'fecha_fin' => '2026-11-06', 'descripcion' => 'Solicitud para actividad académica.'];
}

test('administrator registers reviews closes and reopens preserving a history and historical unit', function () {
    $data = incidencePayload();
    $this->actingAs(incidenceAccount('administrador'))->post(route('incidencias.store'), [...$data, 'estado' => 'Cerrada'])->assertSessionHasNoErrors();
    $item = DB::table('incidencias')->first();
    expect($item->estado)->toBe('Registrada');
    expect($item->id_adscripcion)->not->toBeNull();
    $this->get(route('incidencias.show', $item->id_incidencia))->assertInertia(fn (Assert $page) => $page->component('incidencia')->where('unidad', 'Unidad prueba')->has('historial', 1)->missing('incidencia.evidencia'));
    $this->post(route('incidencias.followup', $item->id_incidencia), ['revision' => 0, 'estado' => 'En revisión', 'nota' => 'Se revisa solicitud.'])->assertSessionHasNoErrors();
    $this->post(route('incidencias.followup', $item->id_incidencia), ['revision' => 1, 'estado' => 'Cerrada', 'nota' => 'Resolución recibida.', 'resultado' => 'Permiso autorizado por Enseñanza mediante oficio 12.'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('incidencias', ['estado' => 'Cerrada', 'revision' => 2]);
    $this->put(route('incidencias.update', $item->id_incidencia), [...$data, 'revision' => 2])->assertForbidden();
    $this->post(route('incidencias.followup', $item->id_incidencia), ['revision' => 2, 'estado' => 'En revisión', 'nota' => 'Aclaración de la resolución.'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('incidencias', ['estado' => 'En revisión', 'fecha_cierre' => null, 'resultado' => null]);
    $this->assertDatabaseCount('seguimientos_incidencias', 4);
    expect(DB::table('seguimientos_incidencias')->where('estado_nuevo', 'Cerrada')->value('nota'))->toContain('Permiso autorizado');
});

test('only administrator publishes PDF offices and only owner can download them', function () {
    Storage::fake('incidencias');
    $data = incidencePayload();
    $personal = PersonalFormacion::findOrFail($data['id_personal']);
    $admin = incidenceAccount('administrador');
    $this->actingAs($admin)->post(route('incidencias.store'), $data)->assertSessionHasNoErrors();
    $id = DB::table('incidencias')->value('id_incidencia');
    $office = ['clase' => 'oficio', 'asunto' => 'Permiso para actividades', 'numero_oficio' => 'JS09/12', 'fecha_oficio' => '2026-11-05', 'archivo' => UploadedFile::fake()->create('permiso.pdf', 30, 'application/pdf')];
    $this->post(route('incidencias.attachment', $id), $office)->assertSessionHasNoErrors();
    $file = DB::table('adjuntos_incidencias')->first();
    Storage::disk('incidencias')->assertExists($file->ruta_archivo);
    $this->actingAs(incidenceAccount('pasante', $personal->id_persona))->get(route('incidencias.show', $id))->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('canReview', false)->where('archivos.0.numero_oficio', 'JS09/12')->missing('archivos.0.ruta_archivo'));
    $this->get(route('incidencias.download', [$id, $file->id_adjunto]))->assertDownload('permiso.pdf');
    $this->post(route('incidencias.attachment', $id), $office)->assertForbidden();
    $this->post(route('incidencias.followup', $id), [])->assertForbidden();
    $this->actingAs(incidenceAccount('pasante', PersonalFormacion::factory()->create()->id_persona))->get(route('incidencias.show', $id))->assertNotFound();
    $this->get(route('incidencias.download', [$id, $file->id_adjunto]))->assertNotFound();
    $this->get(route('incidencias.index'))->assertInertia(fn (Assert $page) => $page->has('incidencias.data', 0));
    $advisor = Asesor::factory()->create();
    AsignacionAsesor::factory()->create(['id_asesor' => $advisor->id_asesor, 'id_personal' => $personal->id_personal]);
    $this->actingAs(incidenceAccount('asesor', $advisor->id_persona))->post(route('incidencias.attachment', $id), $office)->assertForbidden();
});

test('advisor can register and follow assigned trainees but cannot decide results or access others', function () {
    $data = incidencePayload();
    $other = incidencePayload();
    $advisor = Asesor::factory()->create();
    AsignacionAsesor::factory()->create(['id_asesor' => $advisor->id_asesor, 'id_personal' => $data['id_personal']]);
    $this->actingAs(incidenceAccount('asesor', $advisor->id_persona))->post(route('incidencias.store'), $data)->assertSessionHasNoErrors();
    $id = DB::table('incidencias')->value('id_incidencia');
    $this->post(route('incidencias.store'), $other)->assertNotFound();
    $this->post(route('incidencias.followup', $id), ['revision' => 0, 'estado' => 'Registrada', 'nota' => 'Se recibió el justificante.'])->assertSessionHasNoErrors();
    $this->post(route('incidencias.followup', $id), ['revision' => 1, 'estado' => 'En revisión', 'nota' => 'Cambio no autorizado.'])->assertForbidden();
    $this->get(route('incidencias.index'))->assertInertia(fn (Assert $page) => $page->has('incidencias.data', 1));
    AsignacionAsesor::query()->update(['fecha_fin' => today()->subDay()]);
    $this->get(route('incidencias.show', $id))->assertNotFound();
});

test('dates deleted catalogs stale updates and invalid transitions are rejected', function () {
    $data = incidencePayload();
    $this->actingAs(incidenceAccount('administrador'))->post(route('incidencias.store'), [...$data, 'fecha_fin' => '2027-01-01'])->assertSessionHasErrors('fecha_hecho');
    $this->post(route('incidencias.store'), [...$data, 'fecha_fin' => '2026-01-01'])->assertSessionHasErrors('fecha_fin');
    $this->post(route('incidencias.store'), $data)->assertSessionHasNoErrors();
    $id = DB::table('incidencias')->value('id_incidencia');
    $this->post(route('incidencias.followup', $id), ['revision' => 0, 'estado' => 'Cerrada', 'nota' => 'Sin revisión', 'resultado' => 'Cierre'])->assertSessionHasErrors('estado');
    $this->put(route('incidencias.update', $id), [...$data, 'revision' => 0, 'descripcion' => 'Descripción corregida'])->assertSessionHasNoErrors();
    $this->put(route('incidencias.update', $id), [...$data, 'revision' => 0])->assertSessionHasErrors('revision');
    DB::table('tipos_incidencias')->where('id_tipo_incidencia', $data['id_tipo_incidencia'])->update(['deleted_at' => now()]);
    $this->post(route('incidencias.store'), $data)->assertSessionHasErrors('id_tipo_incidencia');
    $this->assertDatabaseHas('incidencias', ['descripcion' => 'Descripción corregida']);
    $this->assertDatabaseCount('incidencias', 1);
});

test('evidence is linked once from the same person and cannot be retired afterwards', function () {
    Storage::fake('expedientes');
    $data = incidencePayload();
    $personal = PersonalFormacion::findOrFail($data['id_personal']);
    $user = incidenceAccount('pasante', $personal->id_persona);
    foreach (['ver', 'registrar', 'eliminar'] as $action) {
        $permission = Permiso::firstOrCreate(['codigo' => 'expedientes.'.$action], ['nombre' => $action]);
        $user->roles()->first()->permisos()->syncWithoutDetaching([$permission->id]);
    }
    $this->actingAs($user)->post(route('expedientes.store', [$personal->id_personal, 10]), ['archivo' => UploadedFile::fake()->create('justificante.pdf', 30, 'application/pdf')])->assertSessionHasNoErrors();
    $file = DB::table('archivos_expediente')->first();
    $admin = incidenceAccount('administrador');
    $this->actingAs($admin)->post(route('incidencias.store'), $data)->assertSessionHasNoErrors();
    $id = DB::table('incidencias')->value('id_incidencia');
    $this->post(route('incidencias.link', $id), ['id_archivo_expediente' => $file->id_archivo_expediente])->assertSessionHasNoErrors();
    $this->post(route('incidencias.link', $id), ['id_archivo_expediente' => $file->id_archivo_expediente])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('adjuntos_incidencias', 1);
    $this->get(route('incidencias.download', [$id, DB::table('adjuntos_incidencias')->value('id_adjunto')]))->assertDownload('justificante.pdf');
    $other = incidencePayload();
    $this->post(route('incidencias.store'), $other)->assertSessionHasNoErrors();
    $otherId = DB::table('incidencias')->max('id_incidencia');
    $this->post(route('incidencias.link', $otherId), ['id_archivo_expediente' => $file->id_archivo_expediente])->assertSessionHasErrors('id_archivo_expediente');
    $this->actingAs($user)->delete(route('expedientes.destroy', [$personal->id_personal, $file->id_archivo_expediente]))->assertSessionHasErrors('archivo');
    $this->assertDatabaseHas('archivos_expediente', ['id_archivo_expediente' => $file->id_archivo_expediente, 'deleted_at' => null]);
    Storage::disk('expedientes')->assertExists($file->ruta_archivo);
});

test('invalid offices do not create private files', function () {
    Storage::fake('incidencias');
    $data = incidencePayload();
    $this->actingAs(incidenceAccount('administrador'))->post(route('incidencias.store'), $data)->assertSessionHasNoErrors();
    $id = DB::table('incidencias')->value('id_incidencia');
    $this->post(route('incidencias.attachment', $id), ['clase' => 'oficio', 'asunto' => 'Permiso', 'archivo' => UploadedFile::fake()->image('oficio.png')])->assertSessionHasErrors(['archivo', 'numero_oficio', 'fecha_oficio']);
    $this->assertDatabaseCount('adjuntos_incidencias', 0);
    expect(Storage::disk('incidencias')->allFiles())->toBe([]);
});

test('guest and revoked module cannot access incidences', function () {
    $this->get(route('incidencias.index'))->assertRedirect(route('login'));
    $user = incidenceAccount('pasante');
    $this->actingAs($user)->get(route('incidencias.index'))->assertOk();
    $user->roles()->first()->permisos()->detach();
    $this->get(route('incidencias.index'))->assertForbidden();
    $this->get(route('incidencias.download', [1, 1]))->assertForbidden();
});
