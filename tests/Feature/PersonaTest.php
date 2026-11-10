<?php

use App\Models\Persona;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function personaAdmin(): User
{
    return User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
}

test('selected profile is saved with the person without creating a login or assigning roles', function (string $profile) {
    $admin = personaAdmin();
    $this->actingAs($admin);
    $type = DB::table('tipos_personal')->insertGetId(['nombre' => 'Servicio social']);
    $payload = ['nombre' => 'Ana', 'ap' => 'Luna', 'perfil' => $profile, 'id_tipo_personal' => $type, 'fecha_ingreso' => '2026-10-01', 'fecha_termino' => '2027-09-30'];
    $this->post(route('personas.store'), $payload)->assertSessionHasNoErrors();
    $person = Persona::sole();
    $table = $profile === 'asesor' ? 'asesores' : 'personal_formacion';
    $other = $profile === 'asesor' ? 'personal_formacion' : 'asesores';
    $this->assertDatabaseHas($table, ['id_persona' => $person->id_persona]);
    $this->assertDatabaseCount($other, 0);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('usuario_rol', 1);
    if ($profile === 'personal_formacion') {
        $this->assertDatabaseHas($table, ['id_tipo_personal' => $type, 'estado' => 'En formacion']);
    }
    $this->put(route('personas.update', $person), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount($table, 1);
    $this->get(route('personas.edit', $person))->assertInertia(fn (Assert $page) => $page->has('perfiles', 1));
    $this->get(route('personas.show', $person))->assertInertia(fn (Assert $page) => $page->has('perfiles', 1));
})->with(['asesor', 'personal_formacion']);

test('existing person can receive a profile but a different existing profile cannot be overwritten', function () {
    $this->actingAs(personaAdmin());
    $person = Persona::factory()->create(['nombre' => 'Ana']);
    $this->put(route('personas.update', $person), ['nombre' => 'Ana', 'ap' => 'Luna', 'perfil' => 'asesor'])->assertSessionHasNoErrors();
    $type = DB::table('tipos_personal')->insertGetId(['nombre' => 'Residente']);
    $this->put(route('personas.update', $person), ['nombre' => 'Cambio no guardado', 'ap' => 'Luna', 'perfil' => 'personal_formacion', 'id_tipo_personal' => $type])->assertSessionHasErrors('perfil');
    expect($person->refresh()->nombre)->toBe('Ana');
    $this->assertDatabaseCount('personal_formacion', 0);
    $person->asesor()->first()->delete();
    $this->put(route('personas.update', $person), ['nombre' => 'Ana', 'ap' => 'Luna', 'perfil' => 'asesor'])->assertSessionHasErrors('perfil');
    $this->assertDatabaseCount('asesores', 1);
});

test('invalid profile data never leaves a partially registered person', function () {
    $this->actingAs(personaAdmin());
    $payload = ['nombre' => 'Ana', 'ap' => 'Luna', 'perfil' => 'personal_formacion'];
    $this->post(route('personas.store'), $payload)->assertSessionHasErrors('id_tipo_personal');
    $type = DB::table('tipos_personal')->insertGetId(['nombre' => 'Archivado', 'deleted_at' => now()]);
    $this->post(route('personas.store'), [...$payload, 'id_tipo_personal' => $type])->assertSessionHasErrors('id_tipo_personal');
    DB::table('tipos_personal')->where('id_tipo_personal', $type)->update(['deleted_at' => null]);
    $this->post(route('personas.store'), [...$payload, 'id_tipo_personal' => $type, 'fecha_ingreso' => '2026-10-01', 'fecha_termino' => '2026-09-01'])->assertSessionHasErrors('fecha_termino');
    $this->post(route('personas.store'), [...$payload, 'perfil' => 'administrador'])->assertSessionHasErrors('perfil');
    $this->assertDatabaseCount('personas', 0);
    $this->assertDatabaseCount('personal_formacion', 0);
});

test('residence options and person saves respect the selected municipality', function () {
    $this->actingAs(personaAdmin());
    $municipio = DB::table('municipios')->insertGetId(['nom_mun' => 'Valle de Bravo']);
    $otroMunicipio = DB::table('municipios')->insertGetId(['nom_mun' => 'Otro municipio']);
    $localidad = DB::table('localidades')->insertGetId(['nom_loc' => 'Centro', 'id_municipio' => $municipio]);
    $otraLocalidad = DB::table('localidades')->insertGetId(['nom_loc' => 'Centro', 'id_municipio' => $otroMunicipio]);
    $this->getJson(route('catalogos.options', ['catalogo' => 'localidades', 'id_municipio' => $municipio, 'selected' => $otraLocalidad]))
        ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.id', $localidad);
    $this->getJson(route('catalogos.options', ['catalogo' => 'localidades', 'id_municipio' => $municipio, 'buscar' => 'Sin coincidencias', 'selected' => $localidad]))
        ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.id', $localidad);
    $data = ['nombre' => 'Ana', 'ap' => 'Luna', 'id_municipio' => $municipio, 'id_localidad' => $otraLocalidad];
    $this->post(route('personas.store'), $data)->assertSessionHasErrors('id_localidad');
    $this->post(route('personas.store'), [...$data, 'id_municipio' => null])->assertSessionHasErrors('id_municipio');
    $this->post(route('personas.store'), [...$data, 'id_localidad' => $localidad])->assertSessionHasNoErrors();
    $person = Persona::sole();
    $this->get(route('personas.edit', $person))->assertInertia(fn (Assert $page) => $page->where('municipio', $municipio)->where('persona.id_localidad', $localidad));
    $this->put(route('personas.update', $person), [...$data, 'id_municipio' => $otroMunicipio])->assertSessionHasNoErrors();
    expect($person->refresh()->id_localidad)->toBe($otraLocalidad);
    $this->put(route('personas.update', $person), [...$data, 'id_municipio' => '', 'id_localidad' => ''])->assertSessionHasNoErrors();
    expect($person->refresh()->id_localidad)->toBeNull();
});

test('administrator registers reads and edits a person without creating accounts or roles', function () {
    $admin = personaAdmin();
    $this->actingAs($admin);
    $this->get(route('personas.create'))->assertOk();
    $gender = DB::table('generos')->insertGetId(['nombre' => 'Femenino']);
    $data = ['nombre' => 'Ana', 'ap' => 'Luna', 'am' => 'Rios', 'curp' => 'aaaa000101mmsbbb01', 'fecha_nacimiento' => '2000-01-01', 'id_genero' => $gender];
    $this->post(route('personas.store'), [...$data, 'id_persona' => 999, 'deleted_at' => now(), 'roles' => ['administrador']])->assertSessionHasNoErrors()->assertRedirect();
    $person = Persona::sole();
    expect($person->curp)->toBe('AAAA000101MMSBBB01');
    expect($person->deleted_at)->toBeNull();
    expect($person->id_persona)->not->toBe(999);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('personal_formacion', 0);
    $this->assertDatabaseCount('asesores', 0);
    $admin->forceFill(['id_persona' => $person->id_persona])->save();
    $this->get(route('personas.show', $person))->assertInertia(fn (Assert $page) => $page->component('persona-show')->where('labels.id_genero.label', 'Femenino')->where('usuario.id', $admin->id));
    $this->get(route('personas.edit', $person))->assertInertia(fn (Assert $page) => $page->component('persona-form')->where('persona.fecha_nacimiento', '2000-01-01'));
    $this->put(route('personas.update', $person), [...$data, 'nombre' => 'Ana Maria', 'correo' => 'ana@example.com'])->assertSessionHasNoErrors()->assertRedirect(route('personas.show', $person));
    expect($person->refresh()->nombre)->toBe('Ana Maria');
    expect($admin->fresh()->id_persona)->toBe($person->id_persona);
    expect($admin->hasRole('administrador'))->toBeTrue();
});

test('invalid identity and inactive catalog references are rejected', function () {
    $this->actingAs(personaAdmin());
    $gender = DB::table('generos')->insertGetId(['nombre' => 'Archivado', 'deleted_at' => now()]);
    $this->post(route('personas.store'), ['nombre' => '', 'ap' => '', 'fecha_nacimiento' => now()->addDay()->format('Y-m-d'), 'curp' => 'short', 'correo' => 'incorrecto', 'id_genero' => $gender, 'id_localidad' => 999, 'id_nacionalidad' => 999])->assertSessionHasErrors(['nombre', 'ap', 'fecha_nacimiento', 'curp', 'correo', 'id_genero', 'id_localidad', 'id_nacionalidad']);
    $this->assertDatabaseCount('personas', 0);
});

test('curp cannot duplicate active or deleted people', function (bool $deleted) {
    $this->actingAs(personaAdmin());
    $person = Persona::factory()->create(['curp' => 'AAAA000101MMSBBB01']);
    if ($deleted) {
        $person->delete();
    }
    $this->post(route('personas.store'), ['nombre' => 'Otra', 'ap' => 'Persona', 'curp' => 'aaaa000101mmsbbb01'])->assertSessionHasErrors('curp');
})->with([false, true]);

test('optional identity fields may be cleared and no postal code is stored', function () {
    $this->actingAs(personaAdmin());
    $person = Persona::factory()->create(['telefono' => '123', 'curp' => 'AAAA000101MMSBBB01']);
    $this->put(route('personas.update', $person), ['nombre' => 'Ana', 'ap' => 'Luna', 'telefono' => '', 'curp' => '', 'cp' => '12345'])->assertSessionHasNoErrors();
    expect($person->refresh()->telefono)->toBeNull();
    expect($person->curp)->toBeNull();
});

test('list searches across full name paginates and excludes deleted people', function () {
    $this->actingAs(personaAdmin());
    Persona::factory()->count(16)->create(['nombre' => 'Ana', 'ap' => 'Luna']);
    Persona::factory()->create(['nombre' => 'Oculta'])->delete();
    $this->get(route('personas.index', ['buscar' => 'Ana Luna']))->assertInertia(fn (Assert $page) => $page->component('personas')->has('records.data', 15)->where('records.total', 16)->where('records.last_page', 2));
    $this->get(route('personas.index', ['buscar' => 'Oculta']))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
});

test('operational roles cannot manage people', function (string $role) {
    $user = User::factory()->has(Rol::factory()->state(['codigo' => $role]), 'roles')->create();
    $person = Persona::factory()->create();
    $this->actingAs($user);
    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('personas.'.$action, in_array($action, ['show', 'edit']) ? $person : []))->assertForbidden();
    }
    $this->post(route('personas.store'), ['nombre' => 'Ana', 'ap' => 'Luna'])->assertForbidden();
    $this->put(route('personas.update', $person), ['nombre' => 'Changed', 'ap' => 'Luna'])->assertForbidden();
    expect($person->fresh()->nombre)->toBe($person->nombre);
})->with(['asesor', 'pasante']);

test('guests must log in and deleted persons cannot be viewed or edited', function () {
    $this->get(route('personas.index'))->assertRedirect(route('login'));
    $this->post(route('personas.store'), [])->assertRedirect(route('login'));
    $this->actingAs(personaAdmin());
    $person = Persona::factory()->create();
    $person->delete();
    $this->get(route('personas.show', $person))->assertNotFound();
    $this->get(route('personas.edit', $person))->assertNotFound();
    $this->put(route('personas.update', $person), ['nombre' => 'Ana', 'ap' => 'Luna'])->assertNotFound();
});
