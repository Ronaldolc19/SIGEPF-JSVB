<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function accountWithRole(string $code): User
{
    $role = Rol::firstOrCreate(['codigo' => $code], ['nombre' => ucfirst($code)]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

test('each assigned role receives its own landing and page', function (string $role, string $component) {
    $user = accountWithRole($role);
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($role.'.dashboard'));
    $this->get(route($role.'.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['administrador', 'dashboard'], ['asesor', 'role-dashboard'], ['pasante', 'role-dashboard'],
]);

test('role panels reject accounts with a different role', function (string $accountRole, string $panelRole) {
    $this->actingAs(accountWithRole($accountRole))->get(route($panelRole.'.dashboard'))->assertForbidden();
})->with([
    ['asesor', 'administrador'], ['pasante', 'administrador'], ['asesor', 'pasante'], ['pasante', 'asesor'],
]);

test('accounts without roles see pending access and cannot open administrator routes', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('role-dashboard')->where('role', null));
    $this->get(route('administrador.dashboard'))->assertForbidden();
    $this->get(route('usuarios.index'))->assertForbidden();
});

test('multiple roles prioritize the administrator landing', function () {
    $user = accountWithRole('asesor');
    $user->roles()->attach(Rol::factory()->create(['codigo' => 'administrador']));
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('administrador.dashboard'));
});

test('administrators can persist role assignments and remove access', function () {
    $admin = accountWithRole('administrador');
    $role = Rol::factory()->create(['codigo' => 'asesor']);
    $user = User::factory()->create();
    $this->actingAs($admin)->put(route('usuarios.roles.update', $user), ['roles' => ['asesor'], 'email' => 'other@example.test'])
        ->assertRedirect(route('usuarios.index'));
    $this->assertDatabaseHas('usuario_rol', ['user_id' => $user->id, 'rol_id' => $role->id]);
    expect($user->fresh()->email)->toBe($user->email);
    $this->put(route('usuarios.roles.update', $user), ['roles' => []])->assertRedirect();
    $this->assertDatabaseMissing('usuario_rol', ['user_id' => $user->id]);
    $this->actingAs($user)->get(route('asesor.dashboard'))->assertForbidden();
});

test('non administrators cannot assign roles even with a forged permission', function (string $role) {
    $user = accountWithRole($role);
    $user->roles()->first()->permisos()->attach(Permiso::factory()->create(['codigo' => 'acceso.editar']));
    $target = User::factory()->create();
    $this->actingAs($user)->put(route('usuarios.roles.update', $target), ['roles' => ['administrador']])->assertForbidden();
    $this->assertDatabaseMissing('usuario_rol', ['user_id' => $target->id]);
})->with(['asesor', 'pasante']);

test('administrator cannot remove their own administrator role', function () {
    $user = accountWithRole('administrador');
    $this->actingAs($user)->put(route('usuarios.roles.update', $user), ['roles' => []])
        ->assertSessionHasErrors(['roles' => 'No puedes retirar tu propio rol de administrador.']);
    expect($user->hasRole('administrador'))->toBeTrue();
});

test('unknown roles are rejected without changing access', function () {
    $admin = accountWithRole('administrador');
    $target = User::factory()->create();
    $this->actingAs($admin)->put(route('usuarios.roles.update', $target), ['roles' => ['superusuario']])
        ->assertSessionHasErrors(['roles.0' => 'Selecciona un rol válido.']);
    $this->assertDatabaseMissing('usuario_rol', ['user_id' => $target->id]);
});

test('guests cannot inspect users or assign privileges', function () {
    $target = User::factory()->create();
    $this->get(route('usuarios.index'))->assertRedirect(route('login'));
    $this->put(route('usuarios.roles.update', $target), ['roles' => []])->assertRedirect(route('login'));
});

test('permissions are module specific and can be revoked', function () {
    $this->seed(AccessSeeder::class);
    $admin = accountWithRole('administrador');
    $advisor = accountWithRole('asesor');
    $role = $advisor->roles()->first();
    $this->actingAs($admin)->put(route('roles.permisos.update', $role), ['modulos' => ['personal']])->assertRedirect();
    expect(Gate::forUser($advisor)->allows('personal.ver'))->toBeTrue();
    expect(Gate::forUser($advisor)->allows('personal.editar'))->toBeFalse();
    expect(Gate::forUser($advisor)->allows('expedientes.ver'))->toBeFalse();
    $this->put(route('roles.permisos.update', $role), ['modulos' => []])->assertRedirect();
    expect(Gate::forUser($advisor)->allows('personal.ver'))->toBeFalse();
});

test('administrator permissions cannot be reduced through the editor', function () {
    $admin = accountWithRole('administrador');
    $this->actingAs($admin)->put(route('roles.permisos.update', $admin->roles()->first()), ['modulos' => []])->assertForbidden();
    expect(Gate::forUser($admin)->allows('personal.eliminar'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('unknown.permission'))->toBeFalse();
});

test('non administrators cannot edit permissions', function () {
    $advisor = accountWithRole('asesor');
    $this->actingAs($advisor)->put(route('roles.permisos.update', $advisor->roles()->first()), ['modulos' => []])->assertForbidden();
});

test('administrative permissions cannot be granted to operational roles', function () {
    $this->seed(AccessSeeder::class);
    $admin = accountWithRole('administrador');
    $advisor = accountWithRole('asesor');
    $this->actingAs($admin)->put(route('roles.permisos.update', $advisor->roles()->first()), ['modulos' => ['acceso']])
        ->assertSessionHasErrors(['modulos.0' => 'Ese módulo no está disponible para este rol.']);
    expect($advisor->hasPermission('acceso.editar'))->toBeFalse();
});

test('access seeding is repeatable and preserves configured permissions', function () {
    $this->seed(AccessSeeder::class);
    $role = Rol::where('codigo', 'asesor')->firstOrFail();
    $permission = Permiso::where('codigo', 'personal.ver')->firstOrFail();
    $role->permisos()->attach($permission);
    $count = Permiso::count();
    $this->seed(AccessSeeder::class);
    expect(Rol::count())->toBe(3);
    expect(Permiso::count())->toBe($count);
    $this->assertDatabaseHas('rol_permiso', ['rol_id' => $role->id, 'permiso_id' => $permission->id]);
});

test('administrator seeder uses configured credentials and never resets existing passwords', function () {
    config(['access.admin.name' => 'Administrador', 'access.admin.email' => 'admin@example.test', 'access.admin.password' => 'SecureExample123!']);
    $this->seed(AdminUserSeeder::class);
    $user = User::where('email', 'admin@example.test')->firstOrFail();
    expect($user->hasRole('administrador'))->toBeTrue();
    expect(Hash::check('SecureExample123!', $user->password))->toBeTrue();
    config(['access.admin.password' => 'DifferentExample123!']);
    $this->seed(AdminUserSeeder::class);
    expect(User::count())->toBe(1);
    expect(Hash::check('SecureExample123!', $user->fresh()->password))->toBeTrue();
});

test('missing administrator email does not create a default account', function () {
    config(['access.admin.email' => null, 'access.admin.password' => null]);
    $this->seed(AdminUserSeeder::class);
    expect(User::count())->toBe(0);
});

test('registration cannot grant roles supplied by the visitor', function () {
    $this->post(route('register'), [
        'name' => 'Visitante', 'email' => 'visitor@example.test', 'password' => 'password',
        'password_confirmation' => 'password', 'roles' => ['administrador'],
    ])->assertRedirect(route('dashboard', absolute: false));
    expect(User::where('email', 'visitor@example.test')->firstOrFail()->roles()->count())->toBe(0);
});

test('administrator can see real accounts and the permission matrix', function () {
    $this->seed(AccessSeeder::class);
    $admin = accountWithRole('administrador');
    $this->actingAs($admin)->get(route('usuarios.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('user-roles')->has('roles', 3)->has('users.data', 1)->missing('users.data.0.password'));
});

test('assigned accounts cannot delete themselves and retain their session', function () {
    $user = accountWithRole('administrador');
    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('the console role assignment preserves existing roles and is repeatable', function () {
    $user = accountWithRole('asesor');
    Rol::factory()->create(['codigo' => 'administrador']);
    $this->artisan('access:assign-role', ['email' => $user->email, 'role' => 'administrador'])->assertSuccessful();
    $this->artisan('access:assign-role', ['email' => $user->email, 'role' => 'administrador'])->assertSuccessful();
    expect($user->roles()->count())->toBe(2);
});

test('the console refuses unknown roles and nonexistent accounts', function () {
    $user = accountWithRole('asesor');
    $this->artisan('access:assign-role', ['email' => $user->email, 'role' => 'unknown'])->assertFailed();
    $this->artisan('access:assign-role', ['email' => 'missing@example.test', 'role' => 'asesor'])->assertFailed();
    expect($user->roles()->count())->toBe(1);
});
