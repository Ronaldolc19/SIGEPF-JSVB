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

function calendarPromotion(string $name, string $state = 'En curso'): int
{
    return DB::table('promociones')->insertGetId(['nombre' => $name, 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-07-31', 'estado' => $state, 'permite_inscripciones' => false]);
}

function calendarAccount(string $role, ?int $person = null): User
{
    $rol = Rol::firstOrCreate(['codigo' => $role], ['nombre' => ucfirst($role)]);
    foreach (['personal.ver', 'expedientes.ver', 'incidencias.ver'] as $code) {
        $permission = Permiso::firstOrCreate(['codigo' => $code], ['nombre' => $code]);
        $rol->permisos()->syncWithoutDetaching([$permission->id]);
    }

    return User::factory()->hasAttached($rol, [], 'roles')->create(['id_persona' => $person]);
}

test('calendar selection persists across personal expediente incidence and dashboard pages', function () {
    $first = calendarPromotion('Primera');
    $second = calendarPromotion('Segunda', 'Cerrada');
    $own = PersonalFormacion::factory()->create(['id_promocion' => $first]);
    $other = PersonalFormacion::factory()->create(['id_promocion' => $second]);
    $type = DB::table('tipos_incidencias')->insertGetId(['nombre' => 'Permiso', 'id_categoria' => DB::table('categorias')->insertGetId(['nombre' => 'Permisos'])]);
    foreach ([$own, $other] as $person) {
        DB::table('incidencias')->insert(['id_personal' => $person->id_personal, 'id_tipo_incidencia' => $type, 'fecha_hecho' => '2026-11-10', 'fecha_registro' => '2026-11-10', 'descripcion' => 'Ejemplo', 'estado' => 'Registrada']);
    }
    $admin = calendarAccount('administrador');
    $this->actingAs($admin)->post(route('promociones.select'), ['promocion' => (string) $second])->assertSessionHasNoErrors()->assertRedirect(route('personal.index'))->assertSessionHas('promocion_consulta.'.$admin->id, (string) $second);
    foreach (['personal.index', 'expedientes.index'] as $name) {
        $this->get(route($name))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $other->id_personal)->where('promotionCalendar.selected', (string) $second)->missing('promotionOptions'));
    }
    $this->get(route('incidencias.index'))->assertInertia(fn (Assert $page) => $page->has('incidencias.data', 1)->where('incidencias.data.0.nombre', $other->persona->nombre));
    $this->get(route('administrador.dashboard'))->assertInertia(fn (Assert $page) => $page->where('promotionCalendar.label', 'Segunda')->has('promotionCalendar.options', 2));
    $this->get(route('expedientes.index', ['buscar' => '']))->assertInertia(fn (Assert $page) => $page->where('promotionCalendar.selected', (string) $second));
    $this->post(route('promociones.select'), ['promocion' => (string) $first, 'destino' => 'expedientes.index'])->assertRedirect(route('expedientes.index'));
    $this->get(route('expedientes.index'))->assertInertia(fn (Assert $page) => $page->where('participaciones.data.0.id_personal', $own->id_personal));
});

test('trainee calendar contains only own promotions and periods including extensions', function () {
    $first = calendarPromotion('Mi promoción', 'Cerrada');
    $second = calendarPromotion('Otra promoción');
    $own = PersonalFormacion::factory()->create(['id_promocion' => $first, 'fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2027-09-30']);
    PersonalFormacion::factory()->create(['id_promocion' => $second]);
    $user = calendarAccount('pasante', $own->id_persona);
    $this->actingAs($user)->get(route('pasante.dashboard'))->assertInertia(fn (Assert $page) => $page->has('promotionCalendar.options', 1)->where('promotionCalendar.options.0.id_promocion', $first)
        ->where('promotionCalendar.selected', (string) $first)->has('promotionCalendar.participaciones', 1)->where('promotionCalendar.participaciones.0.fecha_termino', '2027-09-30'));
    $this->post(route('promociones.select'), ['promocion' => (string) $second])->assertSessionHasErrors('promocion');
    $this->get(route('personal.index', ['promocion' => $second]))->assertSessionHasErrors('promocion');
    $this->post(route('promociones.select'), ['promocion' => (string) $first])->assertSessionHasNoErrors();
    $this->get(route('personal.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $own->id_personal));
});

test('trainee may switch between own promotions without losing historical access', function () {
    $first = calendarPromotion('Anterior', 'Cerrada');
    $second = calendarPromotion('Actual');
    $own = PersonalFormacion::factory()->create(['id_promocion' => $first]);
    $current = PersonalFormacion::factory()->create(['id_promocion' => $second, 'id_persona' => $own->id_persona]);
    $this->actingAs(calendarAccount('pasante', $own->id_persona))->get(route('pasante.dashboard'))->assertInertia(fn (Assert $page) => $page->has('promotionCalendar.options', 2));
    $this->post(route('promociones.select'), ['promocion' => (string) $first])->assertSessionHasNoErrors();
    $this->get(route('personal.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $own->id_personal));
    $this->post(route('promociones.select'), ['promocion' => (string) $second])->assertSessionHasNoErrors();
    $this->get(route('expedientes.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $current->id_personal));
});

test('revoked person link clears stale selection and does not share previous membership', function () {
    $id = calendarPromotion('Privada');
    $person = PersonalFormacion::factory()->create(['id_promocion' => $id]);
    $user = calendarAccount('pasante', $person->id_persona);
    $this->actingAs($user)->post(route('promociones.select'), ['promocion' => (string) $id])->assertSessionHasNoErrors();
    $user->id_persona = null;
    $user->save();
    $this->actingAs($user->fresh())->get(route('pasante.dashboard'))->assertInertia(fn (Assert $page) => $page->has('promotionCalendar.options', 0)->has('promotionCalendar.participaciones', 0)->where('promotionCalendar.selected', 'todas'));
    $this->get(route('expedientes.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 0));
});

test('legacy promotion links synchronize the header and selection is user specific', function () {
    $id = calendarPromotion('Histórica', 'Cerrada');
    $admin = calendarAccount('administrador');
    $this->actingAs($admin)->get(route('personal.index', ['promocion' => $id]))->assertInertia(fn (Assert $page) => $page->where('promotionCalendar.selected', (string) $id));
    $this->get(route('administrador.dashboard'))->assertInertia(fn (Assert $page) => $page->where('promotionCalendar.selected', (string) $id));
    $other = calendarAccount('administrador');
    $this->actingAs($other)->get(route('administrador.dashboard'))->assertInertia(fn (Assert $page) => $page->where('promotionCalendar.selected', 'vigentes'));
    DB::table('promociones')->where('id_promocion', $id)->update(['deleted_at' => now()]);
    $this->actingAs($admin)->get(route('administrador.dashboard'))->assertInertia(fn (Assert $page) => $page->where('promotionCalendar.selected', 'vigentes')->has('promotionCalendar.options', 0));
});

test('calendar selection requires a supported role and accepts only internal allowed destinations', function () {
    $this->post(route('promociones.select'), ['promocion' => 'todas'])->assertRedirect(route('login'));
    $this->actingAs(calendarAccount('asesor'))->post(route('promociones.select'), ['promocion' => 'todas'])->assertForbidden();
    $this->actingAs(calendarAccount('administrador'))->post(route('promociones.select'), ['promocion' => 'todas', 'destino' => 'https://example.com'])->assertSessionHasErrors('destino');
    $user = User::factory()->has(Rol::factory()->state(['codigo' => 'pasante']), 'roles')->create();
    $this->actingAs($user)->post(route('promociones.select'), ['promocion' => 'todas', 'destino' => 'personal.index'])->assertRedirect(route('dashboard'));
});
