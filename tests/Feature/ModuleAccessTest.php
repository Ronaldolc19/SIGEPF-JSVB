<?php

use App\Models\Asesor;
use App\Models\AsignacionAsesor;
use App\Models\Permiso;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('module assignment persists only the actions allowed to the role', function (string $code, array $modules, array $expected) {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $role = Rol::factory()->create(['codigo' => $code]);
    $role->permisos()->attach(Permiso::factory()->create(['codigo' => 'personal.eliminar']));
    $users = User::factory()->count(2)->create();
    foreach ($users as $user) {
        $user->roles()->attach($role);
    }
    $this->actingAs($admin)->put(route('roles.permisos.update', $role), ['modulos' => $modules])->assertRedirect(route('usuarios.index'));
    expect($role->permisos()->pluck('codigo')->all())->toEqualCanonicalizing($expected);
    foreach ($users as $user) {
        foreach ($expected as $permission) {
            expect(Gate::forUser($user)->allows($permission))->toBeTrue();
        }
        expect(Gate::forUser($user)->allows('personal.eliminar'))->toBeFalse();
        expect(Gate::forUser($user)->allows('consulta.publicar'))->toBeFalse();
    }
})->with([
    ['asesor', ['personal', 'incidencias', 'productividad', 'academico'], ['personal.ver', 'incidencias.ver', 'incidencias.registrar', 'incidencias.editar', 'incidencias.revisar', 'productividad.ver', 'productividad.registrar', 'productividad.editar', 'academico.ver', 'academico.revisar']],
    ['pasante', ['personal', 'consulta'], ['personal.ver', 'consulta.ver']],
]);

test('disabled modules disappear from navigation data and direct urls are forbidden after revocation', function () {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $role = Rol::factory()->create(['codigo' => 'pasante']);
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $this->actingAs($admin)->put(route('roles.permisos.update', $role), ['modulos' => ['personal']])->assertRedirect();
    $this->actingAs($user)->get(route('pasante.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.modulesByRole.pasante', ['personal'])->where('auth.canViewPersonal', true));
    $this->get(route('personal.index'))->assertOk();
    $this->actingAs($admin)->put(route('roles.permisos.update', $role), ['modulos' => []])->assertRedirect();
    expect($role->permisos()->count())->toBe(0);
    $this->actingAs($user)->get(route('personal.index'))->assertForbidden();
    $this->get(route('pasante.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.modulesByRole.pasante', []));
});

test('invalid module requests preserve the previous assignment', function (array $payload, string $error) {
    $admin = User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
    $role = Rol::factory()->create(['codigo' => 'pasante']);
    $role->permisos()->attach(Permiso::factory()->create(['codigo' => 'personal.ver']));
    $this->actingAs($admin)->put(route('roles.permisos.update', $role), $payload)->assertSessionHasErrors($error);
    expect($role->permisos()->pluck('codigo')->all())->toBe(['personal.ver']);
})->with([
    [[], 'modulos'],
    [['modulos' => 'personal'], 'modulos'],
    [['modulos' => ['personal', 'personal']], 'modulos.0'],
    [['modulos' => ['inventado']], 'modulos.0'],
    [['modulos' => ['academico']], 'modulos.0'],
    [['modulos' => ['catalogos']], 'modulos.0'],
    [['modulos' => ['personal'], 'permisos' => ['personal.eliminar']], 'permisos'],
]);

test('guests and operational roles cannot configure modules', function (?string $code) {
    $role = Rol::factory()->create(['codigo' => 'pasante']);
    if ($code !== null) {
        $user = User::factory()->create();
        $user->roles()->attach($code === 'pasante' ? $role : Rol::factory()->create(['codigo' => $code]));
        $this->actingAs($user);
    }
    $response = $this->put(route('roles.permisos.update', $role), ['modulos' => ['personal']]);
    if ($code === null) {
        $response->assertRedirect(route('login'));
    } else {
        $response->assertForbidden();
    }
    expect($role->permisos()->count())->toBe(0);
})->with([null, 'asesor', 'pasante']);

test('legacy grants cannot exceed role action restrictions or enable a different role module', function () {
    $role = Rol::factory()->create(['codigo' => 'pasante']);
    foreach (['personal.ver', 'personal.eliminar', 'incidencias.ver'] as $code) {
        $role->permisos()->attach(Permiso::factory()->create(['codigo' => $code]));
    }
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $user->roles()->attach(Rol::factory()->create(['codigo' => 'asesor']));
    expect(Gate::forUser($user)->allows('personal.ver'))->toBeTrue();
    expect(Gate::forUser($user)->allows('personal.eliminar'))->toBeFalse();
    expect(Gate::forUser($user)->allows('incidencias.ver'))->toBeTrue();
    expect(Gate::forUser($user)->allows('incidencias.registrar'))->toBeFalse();
    $this->actingAs($user)->get(route('asesor.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.modulesByRole.asesor', []));
});

test('multiple roles combine only scopes whose module is enabled', function (string $enabledRole) {
    $advisor = Asesor::factory()->create();
    $user = User::factory()->create(['id_persona' => $advisor->id_persona]);
    foreach (['asesor', 'pasante'] as $code) {
        $role = Rol::factory()->create(['codigo' => $code]);
        $user->roles()->attach($role);
        if ($code === $enabledRole) {
            $role->permisos()->attach(Permiso::factory()->create(['codigo' => 'personal.ver']));
        }
    }
    $own = PersonalFormacion::factory()->for($advisor->persona, 'persona')->create();
    $assigned = PersonalFormacion::factory()->create();
    AsignacionAsesor::factory()->for($advisor, 'asesor')->for($assigned, 'personal')->create();
    $allowed = $enabledRole === 'asesor' ? $assigned : $own;
    $denied = $enabledRole === 'asesor' ? $own : $assigned;
    $this->actingAs($user)->get(route('personal.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $allowed->id_personal));
    $this->get(route('personal.show', $denied->id_personal))->assertNotFound();
})->with(['asesor', 'pasante']);
