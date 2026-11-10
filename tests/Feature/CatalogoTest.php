<?php

use App\Models\Permiso;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\TipoPersonal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function catalogAdmin(): User
{
    return User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
}

/** @return array<string, mixed> */
function catalogPayload(string $catalogo): array
{
    return match ($catalogo) {
        'programas' => ['nombre' => 'Programa de prueba', 'clave_programa' => 'PRUEBA'],
        'municipios' => ['nom_mun' => 'Valle de Bravo'],
        'localidades' => ['nom_loc' => 'Centro', 'id_municipio' => DB::table('municipios')->insertGetId(['nom_mun' => 'Valle de Bravo'])],
        'nacionalidades' => ['nombre' => 'Mexicana', 'codigo_iso' => 'MEX'],
        'tipos_incidencias' => ['nombre' => 'Permiso', 'id_categoria' => DB::table('categorias')->insertGetId(['nombre' => 'Administrativa'])],
        'unidades_medicas' => [
            'id_localidad' => DB::table('localidades')->insertGetId(catalogPayload('localidades')),
            'id_jurisdiccion' => DB::table('jurisdicciones')->insertGetId(['nombre' => 'Valle de Bravo']),
            'id_tipo_unidad' => DB::table('tipo_unidades')->insertGetId(['nombre' => 'Centro de salud']),
            'id_tipologia' => DB::table('tipologias_unidades')->insertGetId(['nombre' => 'Rural']),
            'clave_clues' => 'TESTCLUES001', 'nom_unidad' => 'Unidad de prueba', 'domicilio' => 'Domicilio de prueba',
            'referencias' => null, 'cp' => '01234', 'horario' => '08:00 a 16:00',
        ],
        default => ['nombre' => 'Registro de prueba'],
    };
}

test('each catalog supports persisted creation editing listing and soft deletion', function (string $catalogo, string $key, string $name) {
    $this->actingAs(catalogAdmin());
    $payload = catalogPayload($catalogo);
    $this->get(route('catalogos.create', $catalogo))->assertOk();
    $this->post(route('catalogos.store', $catalogo), [...$payload, 'deleted_at' => now(), 'id' => 800])
        ->assertRedirect(route('catalogos.show', $catalogo));
    $row = DB::table($catalogo)->where($name, $payload[$name])->first();
    expect($row)->not->toBeNull();
    expect($row->deleted_at)->toBeNull();
    expect($row->created_at)->not->toBeNull();
    $id = $row->{$key};
    $this->get(route('catalogos.edit', [$catalogo, $id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('catalogo-form')->where('record.'.$name, $payload[$name]));
    $payload[$name] = 'Registro actualizado';
    $this->put(route('catalogos.update', [$catalogo, $id]), $payload)->assertRedirect(route('catalogos.show', $catalogo));
    $this->assertDatabaseHas($catalogo, [$key => $id, $name => 'Registro actualizado']);
    $this->get(route('catalogos.show', $catalogo))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('catalogo-list')->has('records.data', 1)->where('records.data.0.'.$name, 'Registro actualizado'));
    $this->delete(route('catalogos.destroy', [$catalogo, $id]))->assertRedirect();
    expect(DB::table($catalogo)->where($key, $id)->value('deleted_at'))->not->toBeNull();
    $this->get(route('catalogos.show', $catalogo))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
    $this->get(route('catalogos.edit', [$catalogo, $id]))->assertNotFound();
})->with([
    ['entidades_federativas', 'id_entidad_federativa', 'nombre'], ['instituciones_salud', 'id_institucion_salud', 'nombre'],
    ['programas', 'id_programa', 'nombre'], ['vertientes', 'id_vertiente', 'nombre'],
    ['tipos_campos_clinicos', 'id_tipo_campo_clinico', 'nombre'], ['fuentes_financiamiento_beca', 'id_fuente_financiamiento_beca', 'nombre'],
    ['municipios', 'id_municipio', 'nom_mun'], ['localidades', 'id_localidad', 'nom_loc'],
    ['jurisdicciones', 'id_jurisdiccion', 'nombre'], ['tipo_unidades', 'id_tipo_unidad', 'nombre'],
    ['tipologias_unidades', 'id_tipologia', 'nombre'], ['unidades_medicas', 'id_unidad', 'nom_unidad'],
    ['generos', 'id_genero', 'nombre'], ['nacionalidades', 'id_nacionalidad', 'nombre'],
    ['tipos_personal', 'id_tipo_personal', 'nombre'], ['instituciones', 'id_institucion', 'nombre'],
    ['carreras', 'id_carrera', 'nombre'], ['nivel_academico', 'id_nivel_academico', 'nombre'],
    ['categorias', 'id_categoria', 'nombre'], ['tipos_incidencias', 'id_tipo_incidencia', 'nombre'],
    ['tipos_consultas', 'id_tipo_consulta', 'nombre'], ['tipos_documentos', 'id_tipo_documento', 'nombre'],
]);

test('catalog routes reject operational roles even if a catalog permission was directly attached', function (string $code) {
    $role = Rol::factory()->create(['codigo' => $code]);
    $role->permisos()->attach(Permiso::factory()->create(['codigo' => 'catalogos.editar']));
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $id = DB::table('municipios')->insertGetId(['nom_mun' => 'Original']);
    $this->actingAs($user)->get(route('catalogos.index'))->assertForbidden();
    $this->get(route('catalogos.show', 'municipios'))->assertForbidden();
    $this->get(route('catalogos.create', 'municipios'))->assertForbidden();
    $this->get(route('catalogos.edit', ['municipios', $id]))->assertForbidden();
    $this->get(route('catalogos.options', 'municipios'))->assertForbidden();
    $this->post(route('catalogos.store', 'municipios'), ['nom_mun' => 'Nuevo'])->assertForbidden();
    $this->put(route('catalogos.update', ['municipios', $id]), ['nom_mun' => 'Modificado'])->assertForbidden();
    $this->delete(route('catalogos.destroy', ['municipios', $id]))->assertForbidden();
    $this->assertDatabaseHas('municipios', ['id_municipio' => $id, 'nom_mun' => 'Original', 'deleted_at' => null]);
})->with(['asesor', 'pasante']);

test('guest users cannot view or modify catalogs', function () {
    $this->get(route('catalogos.index'))->assertRedirect(route('login'));
    $this->post(route('catalogos.store', 'municipios'), ['nom_mun' => 'Nuevo'])->assertRedirect(route('login'));
});

test('unknown catalogs and missing or deleted rows are not accessible', function () {
    $this->actingAs(catalogAdmin());
    $this->get(route('catalogos.show', 'users'))->assertNotFound();
    $this->post(route('catalogos.store', 'users'), ['name' => 'No'])->assertNotFound();
    $this->get(route('catalogos.options', 'users'))->assertNotFound();
    $this->put(route('catalogos.update', ['municipios', 9000]), ['nom_mun' => 'No'])->assertNotFound();
    $id = DB::table('municipios')->insertGetId(['nom_mun' => 'Eliminado', 'deleted_at' => now()]);
    $this->put(route('catalogos.update', ['municipios', $id]), ['nom_mun' => 'No'])->assertNotFound();
    $this->delete(route('catalogos.destroy', ['municipios', $id]))->assertNotFound();
});

test('required names and maximum lengths have Spanish errors', function (array $payload, string $message) {
    $this->actingAs(catalogAdmin())->post(route('catalogos.store', 'municipios'), $payload)
        ->assertSessionHasErrors(['nom_mun' => $message]);
    $this->assertDatabaseCount('municipios', 0);
})->with([
    [[], 'El campo Nombre del municipio es obligatorio.'],
    [['nom_mun' => str_repeat('a', 256)], 'El campo Nombre del municipio admite hasta 255 caracteres.'],
]);

test('related records prevent deletion even when the child is soft deleted', function (bool $deletedChild) {
    $payload = catalogPayload('localidades');
    DB::table('localidades')->insert([...$payload, 'deleted_at' => $deletedChild ? now() : null]);
    $this->actingAs(catalogAdmin())->delete(route('catalogos.destroy', ['municipios', $payload['id_municipio']]))
        ->assertSessionHasErrors(['registro' => 'No se puede eliminar: este registro tiene datos relacionados, incluidos registros históricos.']);
    $this->assertDatabaseHas('municipios', ['id_municipio' => $payload['id_municipio'], 'deleted_at' => null]);
})->with([false, true]);

test('operational participation prevents deletion of its personnel type', function () {
    $record = PersonalFormacion::factory()->create();
    $this->actingAs(catalogAdmin())->delete(route('catalogos.destroy', ['tipos_personal', $record->id_tipo_personal]))
        ->assertSessionHasErrors('registro');
    expect(TipoPersonal::find($record->id_tipo_personal))->not->toBeNull();
});

test('missing or soft deleted parent records cannot be selected', function (bool $deleted) {
    $id = $deleted ? DB::table('municipios')->insertGetId(['nom_mun' => 'Eliminado', 'deleted_at' => now()]) : 999;
    $this->actingAs(catalogAdmin())->post(route('catalogos.store', 'localidades'), ['nom_loc' => 'Localidad', 'id_municipio' => $id])
        ->assertSessionHasErrors(['id_municipio' => 'La opción de Municipio no existe o fue eliminada.']);
    $this->assertDatabaseCount('localidades', 0);
})->with([false, true]);

test('CLUES remains unique across deleted records and cp preserves leading zeroes', function () {
    $this->actingAs(catalogAdmin());
    $payload = catalogPayload('unidades_medicas');
    $this->post(route('catalogos.store', 'unidades_medicas'), $payload)->assertRedirect();
    $row = DB::table('unidades_medicas')->first();
    expect($row->cp)->toBe('01234');
    DB::table('unidades_medicas')->where('id_unidad', $row->id_unidad)->update(['deleted_at' => now()]);
    $this->post(route('catalogos.store', 'unidades_medicas'), $payload)->assertSessionHasErrors('clave_clues');
});

test('unit postal code must contain exactly five digits', function (string $cp) {
    $payload = catalogPayload('unidades_medicas');
    $this->actingAs(catalogAdmin())->post(route('catalogos.store', 'unidades_medicas'), [...$payload, 'cp' => $cp])
        ->assertSessionHasErrors(['cp' => 'El código postal debe contener exactamente cinco dígitos.']);
    $this->assertDatabaseCount('unidades_medicas', 0);
})->with(['1234', '12a45']);

test('search filters and pagination are applied to real records', function () {
    $first = DB::table('municipios')->insertGetId(['nom_mun' => 'Municipio A']);
    $second = DB::table('municipios')->insertGetId(['nom_mun' => 'Municipio B']);
    for ($i = 0; $i < 17; $i++) {
        DB::table('localidades')->insert(['id_municipio' => $first, 'nom_loc' => 'Centro '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
    }
    DB::table('localidades')->insert(['id_municipio' => $second, 'nom_loc' => 'Centro de otro municipio']);
    $this->actingAs(catalogAdmin())->get(route('catalogos.show', ['catalogo' => 'localidades', 'id_municipio' => $first, 'buscar' => 'Centro', 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.total', 17)->has('records.data', 2)->where('records.data.0.id_municipio_label', 'Municipio A'));
});

test('options are bounded searchable and preserve the current selection', function () {
    for ($i = 1; $i <= 35; $i++) {
        DB::table('municipios')->insert(['nom_mun' => 'Municipio '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
    }
    $this->actingAs(catalogAdmin())->getJson(route('catalogos.options', ['catalogo' => 'municipios', 'selected' => 35]))->assertOk()->assertJsonCount(31, 'options');
    $this->getJson(route('catalogos.options', ['catalogo' => 'municipios', 'buscar' => '35']))->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.label', 'Municipio 35');
});

test('locality options show municipality and exclude deleted choices', function () {
    $payload = catalogPayload('localidades');
    DB::table('localidades')->insert($payload);
    DB::table('localidades')->insert([...$payload, 'deleted_at' => now()]);
    $this->actingAs(catalogAdmin())->getJson(route('catalogos.options', ['catalogo' => 'localidades', 'buscar' => 'Valle']))
        ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.context', 'Valle de Bravo');
});

test('catalog overview includes adscription catalogs and counts only active records', function () {
    DB::table('municipios')->insert(['nom_mun' => 'Activo']);
    DB::table('municipios')->insert(['nom_mun' => 'Eliminado', 'deleted_at' => now()]);
    $this->actingAs(catalogAdmin())->get(route('catalogos.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('catalogos')->has('catalogos', 22)
            ->where('catalogos', fn ($catalogs) => collect($catalogs)->firstWhere('slug', 'municipios')['count'] === 1));
});
