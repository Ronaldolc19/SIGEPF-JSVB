<?php

use App\Models\Asesor;
use App\Models\AsignacionAsesor;
use App\Models\Permiso;
use App\Models\Persona;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function scopedPersonalAccount(string $code, ?Persona $person = null, bool $permission = true): User
{
    $role = Rol::firstOrCreate(['codigo' => $code], ['nombre' => ucfirst($code)]);
    if ($permission) {
        $grant = Permiso::firstOrCreate(['codigo' => 'personal.ver'], ['nombre' => 'Ver personal']);
        $role->permisos()->syncWithoutDetaching([$grant->id]);
    }
    $user = User::factory()->create(['id_persona' => $person?->id_persona]);
    $user->roles()->attach($role);

    return $user;
}

test('administrator can read all active records without a person link', function () {
    $admin = scopedPersonalAccount('administrador', permission: false);
    PersonalFormacion::factory()->count(2)->create();
    $deleted = PersonalFormacion::factory()->create();
    $deleted->delete();
    $this->actingAs($admin)->get(route('personal.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('personal-records')->has('participaciones.data', 2)->where('isAdmin', true));
});

test('trainee sees all own participations but cannot read another person by changing the url', function () {
    $person = Persona::factory()->create();
    $user = scopedPersonalAccount('pasante', $person);
    $own = PersonalFormacion::factory()->for($person, 'persona')->count(2)->create();
    $other = PersonalFormacion::factory()->create();
    $this->actingAs($user)->get(route('personal.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 2));
    $this->get(route('personal.show', $own->first()->id_personal))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('detail.id_personal', $own->first()->id_personal)->missing('detail.persona.correo'));
    $this->get(route('personal.show', $other->id_personal))->assertNotFound();
    expect(Gate::forUser($user)->allows('view', $other))->toBeFalse();
});

test('advisor sees only currently assigned participations', function () {
    $advisor = Asesor::factory()->create();
    $user = scopedPersonalAccount('asesor', $advisor->persona);
    $assigned = PersonalFormacion::factory()->create();
    AsignacionAsesor::factory()->for($advisor, 'asesor')->for($assigned, 'personal')->create();
    $other = PersonalFormacion::factory()->create();
    AsignacionAsesor::factory()->for($other, 'personal')->create();
    $this->actingAs($user)->get(route('personal.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $assigned->id_personal));
    $this->get(route('personal.show', $assigned->id_personal))->assertOk();
    $this->get(route('personal.show', $other->id_personal))->assertNotFound();
});

test('assignment validity includes today but excludes future expired and deleted assignments', function (string $start, ?string $end, bool $deleted, bool $allowed) {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $advisor = Asesor::factory()->create();
    $user = scopedPersonalAccount('asesor', $advisor->persona);
    $record = PersonalFormacion::factory()->create();
    $assignment = AsignacionAsesor::factory()->for($advisor, 'asesor')->for($record, 'personal')
        ->create(['fecha_asignacion' => $start, 'fecha_fin' => $end, 'deleted_at' => $deleted ? now() : null]);
    expect(Gate::forUser($user)->allows('view', $record))->toBe($allowed);
})->with([
    ['2026-10-01', null, false, true],
    ['2026-09-01', '2026-10-01', false, true],
    ['2026-10-02', null, false, false],
    ['2026-09-01', '2026-09-30', false, false],
    ['2026-09-01', null, true, false],
]);

test('linked accounts without permission cannot read personal data', function () {
    $person = Persona::factory()->create();
    $user = scopedPersonalAccount('pasante', $person, false);
    $own = PersonalFormacion::factory()->for($person, 'persona')->create();
    $this->actingAs($user)->get(route('personal.index'))->assertForbidden();
    $this->get(route('personal.show', $own->id_personal))->assertForbidden();
});

test('accounts without identity links have an empty list', function (string $role) {
    $user = scopedPersonalAccount($role);
    $other = PersonalFormacion::factory()->create();
    $this->actingAs($user)->get(route('personal.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 0)->where('linked', false));
    $this->get(route('personal.show', $other->id_personal))->assertNotFound();
})->with(['asesor', 'pasante']);

test('deleted identities and advisors do not retain access', function (string $deletedType) {
    $advisor = Asesor::factory()->create();
    $user = scopedPersonalAccount('asesor', $advisor->persona);
    $record = PersonalFormacion::factory()->create();
    AsignacionAsesor::factory()->for($advisor, 'asesor')->for($record, 'personal')->create();
    match ($deletedType) {
        'advisor' => $advisor->delete(),
        'identity' => $advisor->persona->delete(),
        'record-person' => $record->persona->delete(),
        'record' => $record->delete(),
    };
    $this->actingAs($user)->get(route('personal.show', $record->id_personal))->assertNotFound();
})->with(['advisor', 'identity', 'record-person', 'record']);

test('multiple roles combine only owned and assigned participations', function () {
    $advisor = Asesor::factory()->create();
    $user = scopedPersonalAccount('asesor', $advisor->persona);
    $traineeRole = Rol::factory()->create(['codigo' => 'pasante']);
    $traineeRole->permisos()->attach(Permiso::where('codigo', 'personal.ver')->firstOrFail());
    $user->roles()->attach($traineeRole);
    PersonalFormacion::factory()->for($advisor->persona, 'persona')->create();
    AsignacionAsesor::factory()->for($advisor, 'asesor')->create();
    PersonalFormacion::factory()->create();
    $this->actingAs($user)->get(route('personal.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 2));
});

test('revoking an assignment immediately prevents further consultation', function () {
    $advisor = Asesor::factory()->create();
    $user = scopedPersonalAccount('asesor', $advisor->persona);
    $assignment = AsignacionAsesor::factory()->for($advisor, 'asesor')->create();
    $record = $assignment->personal;
    expect(Gate::forUser($user)->allows('view', $record))->toBeTrue();
    $assignment->delete();
    $this->actingAs($user)->get(route('personal.show', $record->id_personal))->assertNotFound();
});

test('guest requests cannot read personal information', function () {
    $record = PersonalFormacion::factory()->create();
    $this->get(route('personal.index'))->assertRedirect(route('login'));
    $this->get(route('personal.show', $record->id_personal))->assertRedirect(route('login'));
});
