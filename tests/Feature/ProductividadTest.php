<?php

use App\Models\Asesor;
use App\Models\AsignacionAsesor;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\ProductividadAccessSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
    $this->seed([AccessSeeder::class, ProductividadAccessSeeder::class]);
    $this->person = PersonalFormacion::factory()->create(['fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2027-07-31']);
    $this->trainee = User::factory()->create(['id_persona' => $this->person->id_persona]);
    $this->trainee->roles()->attach(Rol::where('codigo', 'pasante')->first());
    $this->admin = User::factory()->create();
    $this->admin->roles()->attach(Rol::where('codigo', 'administrador')->first());
    $this->activity = DB::table('tipos_consultas')->insertGetId(['nombre' => 'Sesión educativa']);
    $this->payload = ['id_personal' => $this->person->id_personal, 'id_tipo_consulta' => $this->activity, 'fecha_actividad' => '2026-11-10', 'num_actividades' => 5, 'descripcion' => 'Actividad grupal'];
});

test('trainee records an activity and receives a monday to sunday summary', function () {
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('productividad', $this->payload + ['estado' => 'Registrada', 'revision' => 0]);
    $this->get(route('productividad.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('productividad')->where('semana', '2026-11-09')->where('finSemana', '2026-11-15')->has('registros.data', 1)->where('resumen.0.cantidad', 5)->where('registros.data.0.canReview', false));
    $this->get(route('productividad.create'))->assertInertia(fn (Assert $page) => $page->component('productividad-form')->has('participaciones', 1));
});

test('trainee capture resolves the latest own period while admin retains person selection', function () {
    $older = PersonalFormacion::factory()->create(['id_persona' => $this->person->id_persona, 'fecha_ingreso' => '2025-08-01']);
    $this->actingAs($this->trainee)->get(route('productividad.create'))->assertInertia(fn (Assert $page) => $page->where('canSelectPersonal', false)->has('participaciones', 1)->where('participaciones.0.id_personal', $this->person->id_personal));
    $this->actingAs($this->admin)->get(route('productividad.create'))->assertInertia(fn (Assert $page) => $page->where('canSelectPersonal', true)->has('participaciones', 2));
});

test('weekly summary includes all pages but excludes other weeks and deleted entries', function () {
    for ($i = 0; $i < 22; $i++) {
        DB::table('productividad')->insert($this->payload + ['fecha_registro' => now()]);
    }
    DB::table('productividad')->insert(array_replace($this->payload, ['fecha_actividad' => '2026-11-08', 'fecha_registro' => now()]));
    DB::table('productividad')->insert($this->payload + ['deleted_at' => now(), 'fecha_registro' => now()]);
    $this->actingAs($this->trainee)->get(route('productividad.index'))->assertInertia(fn (Assert $page) => $page->has('registros.data', 20)->where('registros.total', 22)->where('resumen.0.cantidad', 110));
});

test('another trainee cannot read modify delete or register someone elses productivity', function () {
    $id = DB::table('productividad')->insertGetId($this->payload + ['fecha_registro' => now()]);
    $other = User::factory()->create(['id_persona' => PersonalFormacion::factory()->create()->id_persona]);
    $other->roles()->attach(Rol::where('codigo', 'pasante')->first());
    $this->actingAs($other)->get(route('productividad.index'))->assertInertia(fn (Assert $page) => $page->has('registros.data', 0)->has('resumen', 0));
    $this->get(route('productividad.edit', $id))->assertNotFound();
    $this->put(route('productividad.update', $id), $this->payload + ['revision' => 0])->assertNotFound();
    $this->delete(route('productividad.destroy', $id), ['revision' => 0])->assertNotFound();
    $this->post(route('productividad.store'), $this->payload)->assertNotFound();
    $this->assertDatabaseHas('productividad', ['id_productividad' => $id, 'deleted_at' => null]);
});

test('activity must match the career and remain available', function () {
    $career = DB::table('carreras')->insertGetId(['nombre' => 'Enfermería']);
    DB::table('tipos_consultas')->where('id_tipo_consulta', $this->activity)->update(['id_carrera' => $career]);
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->payload)->assertSessionHasErrors('id_tipo_consulta');
    DB::table('datos_academicos')->insert(['id_personal' => $this->person->id_personal, 'id_carrera' => $career, 'id_institucion' => DB::table('instituciones')->insertGetId(['nombre' => 'Universidad de prueba'])]);
    $this->post(route('productividad.store'), $this->payload)->assertSessionHasNoErrors();
    DB::table('tipos_consultas')->where('id_tipo_consulta', $this->activity)->update(['deleted_at' => now()]);
    $this->post(route('productividad.store'), array_replace($this->payload, ['fecha_actividad' => '2026-11-09']))->assertSessionHasErrors('id_tipo_consulta');
});

test('invalid dates quantities and duplicate daily activities are rejected', function () {
    $this->actingAs($this->trainee);
    foreach (['2026-07-31', '2026-11-12', 'not-a-date'] as $date) {
        $this->post(route('productividad.store'), array_replace($this->payload, ['fecha_actividad' => $date]))->assertSessionHasErrors('fecha_actividad');
    }
    foreach ([0, -2, 1.5, 100001] as $quantity) {
        $this->post(route('productividad.store'), array_replace($this->payload, ['num_actividades' => $quantity]))->assertSessionHasErrors('num_actividades');
    }
    $this->post(route('productividad.store'), $this->payload)->assertSessionHasNoErrors();
    $this->post(route('productividad.store'), $this->payload)->assertSessionHasErrors('fecha_actividad');
    $this->assertDatabaseCount('productividad', 1);
    $this->person->update(['fecha_termino' => '2026-11-08']);
    $this->post(route('productividad.store'), $this->payload)->assertSessionHasErrors('fecha_actividad');
    $this->person->update(['estado' => 'Concluido']);
    $this->post(route('productividad.store'), array_replace($this->payload, ['fecha_actividad' => '2026-11-07']))->assertSessionHasErrors('fecha_actividad');
});

test('review requires observations for corrections and validated records are locked', function () {
    $id = DB::table('productividad')->insertGetId($this->payload + ['fecha_registro' => now()]);
    $this->actingAs($this->trainee)->post(route('productividad.review', $id), ['estado' => 'Validada', 'revision' => 0])->assertForbidden();
    $this->actingAs($this->admin)->post(route('productividad.review', $id), ['estado' => 'Por corregir', 'revision' => 0])->assertSessionHasErrors('observaciones');
    $this->post(route('productividad.review', $id), ['estado' => 'Validada', 'revision' => 0])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('productividad', ['id_productividad' => $id, 'estado' => 'Validada', 'revisado_por' => $this->admin->id, 'revision' => 1]);
    $this->actingAs($this->trainee)->put(route('productividad.update', $id), $this->payload + ['revision' => 1])->assertForbidden();
    $this->delete(route('productividad.destroy', $id), ['revision' => 1])->assertForbidden();
    $this->actingAs($this->admin)->post(route('productividad.review', $id), ['estado' => 'Por corregir', 'observaciones' => 'Verifica la cantidad.', 'revision' => 1])->assertSessionHasNoErrors();
    $this->actingAs($this->trainee)->put(route('productividad.update', $id), array_replace($this->payload, ['revision' => 2, 'num_actividades' => 3]))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('productividad', ['id_productividad' => $id, 'num_actividades' => 3, 'estado' => 'Registrada', 'revisado_por' => null, 'revision' => 3]);
    $this->put(route('productividad.update', $id), $this->payload + ['revision' => 2])->assertSessionHasErrors('revision');
    $this->delete(route('productividad.destroy', $id), ['revision' => 3])->assertSessionHasNoErrors();
    $this->assertSoftDeleted('productividad', ['id_productividad' => $id]);
});

test('advisor reviews only active assignments and cannot enter activities as the trainee', function () {
    $advisor = Asesor::factory()->create();
    $account = User::factory()->create(['id_persona' => $advisor->id_persona]);
    $account->roles()->attach(Rol::where('codigo', 'asesor')->first());
    $id = DB::table('productividad')->insertGetId($this->payload + ['fecha_registro' => now()]);
    $this->actingAs($account)->get(route('productividad.index'))->assertInertia(fn (Assert $page) => $page->has('registros.data', 0));
    $this->post(route('productividad.review', $id), ['estado' => 'Validada', 'revision' => 0])->assertNotFound();
    $assignment = AsignacionAsesor::factory()->create(['id_personal' => $this->person->id_personal, 'id_asesor' => $advisor->id_asesor]);
    $this->get(route('productividad.index'))->assertInertia(fn (Assert $page) => $page->has('registros.data', 1)->where('registros.data.0.canReview', true)->where('canCreate', false));
    $this->post(route('productividad.review', $id), ['estado' => 'Validada', 'revision' => 0])->assertSessionHasNoErrors();
    $this->post(route('productividad.store'), $this->payload)->assertForbidden();
    $assignment->update(['fecha_fin' => '2026-11-10']);
    $this->post(route('productividad.review', $id), ['estado' => 'Validada', 'revision' => 1])->assertNotFound();
});

test('module permission and account linking protect the endpoints', function () {
    $this->get(route('productividad.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('productividad.index'))->assertForbidden();
    $this->trainee->id_persona = null;
    $this->trainee->save();
    $this->actingAs($this->trainee)->get(route('productividad.index'))->assertInertia(fn (Assert $page) => $page->has('registros.data', 0));
    $this->post(route('productividad.store'), $this->payload)->assertNotFound();
});

test('selected promotion filters records and totals including legacy captures', function () {
    $promotion = DB::table('promociones')->insertGetId(['nombre' => 'Promoción concluida', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-10-31', 'estado' => 'Cerrada']);
    $this->actingAs($this->trainee)->post(route('productividad.store'), $this->payload)->assertSessionHasNoErrors();
    $this->person->update(['id_promocion' => $promotion]);
    $this->actingAs($this->admin)->post(route('promociones.select'), ['promocion' => 'sin', 'destino' => 'productividad.index'])->assertRedirect(route('productividad.index'));
    $this->get(route('productividad.index'))->assertInertia(fn (Assert $page) => $page->has('registros.data', 0)->has('resumen', 0));
    $this->post(route('promociones.select'), ['promocion' => (string) $promotion, 'destino' => 'productividad.index'])->assertSessionHasNoErrors();
    $this->get(route('productividad.index'))->assertInertia(fn (Assert $page) => $page->has('registros.data', 1)->where('resumen.0.cantidad', 5));
});
