<?php

use App\Models\Permiso;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function promotionPayload(): array
{
    return ['nombre' => 'Agosto 2026', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-07-31', 'estado' => 'En curso', 'permite_inscripciones' => true,
        'carreras' => [DB::table('carreras')->insertGetId(['nombre' => 'Medicina'])], 'revision' => 0];
}

test('administrator manages overlapping promotions and closes without changing participants', function () {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $data = promotionPayload();
    $this->actingAs($admin)->post(route('promociones.store'), $data)->assertSessionHasNoErrors();
    $id = DB::table('promociones')->value('id_promocion');
    $personal = PersonalFormacion::factory()->create(['id_promocion' => $id, 'fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2027-09-30']);
    $this->post(route('promociones.store'), [...$data, 'nombre' => 'Febrero 2027', 'fecha_inicio' => '2027-02-01', 'fecha_fin' => '2028-01-31'])->assertSessionHasNoErrors();
    $this->get(route('promociones.index', ['anio' => 2027]))->assertInertia(fn (Assert $page) => $page->component('promociones')->has('calendario', 2)->has('promociones.data', 2));
    $this->get(route('promociones.create'))->assertOk();
    $this->get(route('promociones.edit', $id))->assertOk();
    $this->put(route('promociones.update', $id), [...$data, 'revision' => 1, 'estado' => 'Cerrada'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('promociones', ['id_promocion' => $id, 'permite_inscripciones' => false, 'estado' => 'Cerrada']);
    $this->assertDatabaseHas('personal_formacion', ['id_personal' => $personal->id_personal, 'estado' => 'En formacion', 'fecha_termino' => '2027-09-30']);
    $this->get(route('promociones.show', $id))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1));
    $this->get(route('personal.index', ['promocion' => $id]))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1));
    $this->get(route('personal.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1));
    $this->get(route('personal.index', ['promocion' => 'prorrogas']))->assertInertia(fn (Assert $page) => $page->where('participaciones.data.0.id_personal', $personal->id_personal));
    $this->get(route('expedientes.index', ['promocion' => $id]))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1));
});

test('promotion protects historical dates used careers and stale edits', function () {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $data = promotionPayload();
    $this->actingAs($admin)->post(route('promociones.store'), $data)->assertSessionHasNoErrors();
    $id = DB::table('promociones')->value('id_promocion');
    $personal = PersonalFormacion::factory()->create(['id_promocion' => $id]);
    DB::table('datos_academicos')->insert(['id_personal' => $personal->id_personal, 'id_carrera' => $data['carreras'][0], 'id_institucion' => DB::table('instituciones')->insertGetId(['nombre' => 'Universidad'])]);
    $this->put(route('promociones.update', $id), [...$data, 'revision' => 1, 'fecha_fin' => '2027-08-31'])->assertSessionHasErrors('fecha_inicio');
    $other = DB::table('carreras')->insertGetId(['nombre' => 'Enfermería']);
    $this->put(route('promociones.update', $id), [...$data, 'revision' => 1, 'carreras' => [$other]])->assertSessionHasErrors('carreras');
    $this->put(route('promociones.update', $id), [...$data, 'nombre' => 'Cambio desactualizado'])->assertSessionHasErrors('revision');
    $this->assertDatabaseHas('promociones', ['id_promocion' => $id, 'nombre' => 'Agosto 2026', 'fecha_fin' => '2027-07-31', 'revision' => 1]);
});

test('invalid promotion periods careers and duplicate names do not persist', function () {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $data = promotionPayload();
    $this->actingAs($admin)->post(route('promociones.store'), [...$data, 'fecha_fin' => '2026-01-01', 'carreras' => []])->assertSessionHasErrors(['fecha_fin', 'carreras']);
    $this->assertDatabaseCount('promociones', 0);
    $this->post(route('promociones.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('promociones.store'), $data)->assertSessionHasErrors('nombre');
    DB::table('carreras')->where('id_carrera', $data['carreras'][0])->update(['deleted_at' => now()]);
    $this->post(route('promociones.store'), [...$data, 'nombre' => 'Otra'])->assertSessionHasErrors('carreras.0');
    $this->assertDatabaseCount('promociones', 1);
});

test('guests and operational roles cannot manage promotions', function () {
    $this->get(route('promociones.index'))->assertRedirect(route('login'));
    foreach (['asesor', 'pasante'] as $role) {
        $user = User::factory()->has(Rol::factory()->state(['codigo' => $role]), 'roles')->create();
        $this->actingAs($user)->get(route('promociones.index'))->assertForbidden();
        $this->post(route('promociones.store'), [])->assertForbidden();
        $this->put(route('promociones.update', 1), [])->assertForbidden();
    }
});

test('filters retain legacy records and separate closed history', function () {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $data = promotionPayload();
    $this->actingAs($admin)->post(route('promociones.store'), [...$data, 'estado' => 'Cerrada'])->assertSessionHasNoErrors();
    $id = DB::table('promociones')->value('id_promocion');
    PersonalFormacion::factory()->create(['id_promocion' => $id]);
    $legacy = PersonalFormacion::factory()->create();
    $this->get(route('personal.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $legacy->id_personal));
    $this->get(route('personal.index', ['promocion' => 'todas']))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 2));
    $this->get(route('personal.index', ['promocion' => 99999]))->assertSessionHasErrors('promocion');
});

test('promotion filters never expose another trainees participation or expediente', function () {
    $id = DB::table('promociones')->insertGetId(['nombre' => 'Historial', 'fecha_inicio' => '2025-02-01', 'fecha_fin' => '2026-01-31', 'estado' => 'Cerrada', 'permite_inscripciones' => false]);
    $own = PersonalFormacion::factory()->create(['id_promocion' => $id]);
    $other = PersonalFormacion::factory()->create(['id_promocion' => $id]);
    $role = Rol::factory()->create(['codigo' => 'pasante']);
    foreach (['personal.ver', 'expedientes.ver'] as $code) {
        $role->permisos()->attach(Permiso::factory()->create(['codigo' => $code]));
    }
    $user = User::factory()->hasAttached($role, [], 'roles')->create(['id_persona' => $own->id_persona]);
    foreach (['personal.index', 'expedientes.index'] as $name) {
        $this->actingAs($user)->get(route($name, ['promocion' => $id]))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $own->id_personal));
    }
    $this->get(route('expedientes.show', $other->id_personal))->assertNotFound();
    $this->get(route('personal.show', $other->id_personal))->assertNotFound();
});
