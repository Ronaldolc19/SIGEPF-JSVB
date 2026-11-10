<?php

use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoPersonal;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function accountCreator(): User
{
    return User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
}

test('administrator creates login then registers and links an advisor while staying signed in', function () {
    $admin = accountCreator();
    Rol::factory()->create(['codigo' => 'asesor']);
    $this->actingAs($admin)->get(route('usuarios.create'))->assertOk();
    $this->post(route('usuarios.store'), ['name' => 'Ana Luna', 'email' => 'ana@example.com', 'password' => 'password-for-testing', 'password_confirmation' => 'password-for-testing', 'rol' => 'asesor', 'email_verified_at' => now(), 'id_persona' => 999])->assertSessionHasNoErrors()->assertRedirect();
    $user = User::where('email', 'ana@example.com')->sole();
    expect(Hash::check('password-for-testing', $user->password))->toBeTrue();
    expect($user->id_persona)->toBeNull();
    expect($user->email_verified_at)->toBeNull();
    expect($user->hasRole('asesor'))->toBeTrue();
    $this->assertAuthenticatedAs($admin);
    $this->get(route('personas.create', ['usuario' => $user->id]))->assertInertia(fn (Assert $page) => $page->where('account.id', $user->id)->where('account.perfil', 'asesor')->missing('account.password'));
    $this->post(route('personas.store'), ['nombre' => 'Ana', 'ap' => 'Luna', 'perfil' => 'asesor', 'id_usuario' => $user->id])->assertSessionHasNoErrors();
    $person = Persona::sole();
    expect($user->fresh()->id_persona)->toBe($person->id_persona);
    $this->assertDatabaseHas('asesores', ['id_persona' => $person->id_persona]);
    $this->post(route('personas.store'), ['nombre' => 'Otra', 'ap' => 'Persona', 'id_usuario' => $user->id])->assertSessionHasErrors('id_usuario');
    $this->assertDatabaseCount('personas', 1);
    $this->get(route('personas.create', ['usuario' => $user->id]))->assertRedirect(route('usuarios.persona.edit', $user));
});

test('trainee account can be completed later and failed profile data leaves account unlinked', function () {
    $this->actingAs(accountCreator());
    Rol::factory()->create(['codigo' => 'pasante']);
    $this->post(route('usuarios.store'), ['name' => 'Ana', 'email' => 'trainee@example.com', 'password' => 'password-for-testing', 'password_confirmation' => 'password-for-testing', 'rol' => 'pasante'])->assertSessionHasNoErrors();
    $user = User::where('email', 'trainee@example.com')->sole();
    $this->get(route('personas.create', ['usuario' => $user->id]))->assertInertia(fn (Assert $page) => $page->where('account.perfil', 'personal_formacion'));
    $payload = ['nombre' => 'Ana', 'ap' => 'Luna', 'perfil' => 'personal_formacion', 'id_usuario' => $user->id];
    $this->post(route('personas.store'), $payload)->assertSessionHasErrors('id_tipo_personal');
    expect($user->fresh()->id_persona)->toBeNull();
    $this->assertDatabaseCount('personas', 0);
    $type = TipoPersonal::factory()->create();
    $this->post(route('personas.store'), [...$payload, 'id_tipo_personal' => $type->getKey()])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('personal_formacion', ['id_persona' => $user->fresh()->id_persona, 'id_tipo_personal' => $type->getKey()]);
});

test('account creation validates credentials and cannot overwrite an existing account', function () {
    $admin = accountCreator();
    $this->actingAs($admin)->post(route('usuarios.store'), ['name' => '', 'email' => $admin->email, 'password' => 'short', 'password_confirmation' => 'different', 'rol' => 'inventado'])->assertSessionHasErrors(['name', 'email', 'password', 'rol']);
    $this->assertDatabaseCount('users', 1);
});

test('only administrators may create accounts or link new personal data', function (string $role) {
    $user = User::factory()->has(Rol::factory()->state(['codigo' => $role]), 'roles')->create();
    $this->actingAs($user)->get(route('usuarios.create'))->assertForbidden();
    $this->post(route('usuarios.store'), [])->assertForbidden();
    $this->post(route('personas.store'), ['nombre' => 'Ana', 'ap' => 'Luna', 'id_usuario' => $user->id])->assertForbidden();
})->with(['asesor', 'pasante']);

test('guests must authenticate to create accounts', function () {
    $this->get(route('usuarios.create'))->assertRedirect(route('login'));
    $this->post(route('usuarios.store'), [])->assertRedirect(route('login'));
});
