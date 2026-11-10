<?php

use App\Models\Persona;
use App\Models\Rol;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function personaLinkAdmin(): User
{
    return User::factory()->has(Rol::factory()->state(['codigo' => 'administrador']), 'roles')->create();
}

test('administrator links changes and removes a person without changing roles or credentials', function () {
    $admin = personaLinkAdmin();
    $user = User::factory()->has(Rol::factory()->state(['codigo' => 'pasante']), 'roles')->create();
    $first = Persona::factory()->create();
    $second = Persona::factory()->create();
    $hash = $user->password;
    foreach ([$first->id_persona, $second->id_persona, null] as $id) {
        $this->actingAs($admin)->put(route('usuarios.persona.update', $user), ['id_persona' => $id, 'roles' => ['administrador'], 'password' => 'changed'])
            ->assertRedirect(route('usuarios.index'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'id_persona' => $id]);
        expect($user->fresh()->password)->toBe($hash);
        expect($user->hasRole('administrador'))->toBeFalse();
    }
    expect(Persona::count())->toBe(2);
});

test('linking the current person again is allowed', function () {
    $admin = personaLinkAdmin();
    $person = Persona::factory()->create();
    $user = User::factory()->for($person, 'persona')->create();
    $this->actingAs($admin)->put(route('usuarios.persona.update', $user), ['id_persona' => $person->id_persona])->assertRedirect();
});

test('a person cannot belong to two accounts', function () {
    $admin = personaLinkAdmin();
    $person = Persona::factory()->create();
    $owner = User::factory()->for($person, 'persona')->create();
    $other = User::factory()->create();
    $this->actingAs($admin)->put(route('usuarios.persona.update', $other), ['id_persona' => $person->id_persona])
        ->assertSessionHasErrors(['id_persona' => 'Esta persona ya está vinculada a otra cuenta.']);
    expect($owner->fresh()->id_persona)->toBe($person->id_persona);
    expect($other->fresh()->id_persona)->toBeNull();
});

test('missing or malformed person selection does not silently unlink the account', function (array $payload, string $message) {
    $admin = personaLinkAdmin();
    $user = User::factory()->for(Persona::factory(), 'persona')->create();
    $this->actingAs($admin)->put(route('usuarios.persona.update', $user), $payload)
        ->assertSessionHasErrors(['id_persona' => $message]);
    expect($user->fresh()->id_persona)->toBe($user->id_persona);
})->with([
    [[], 'Selecciona una persona o indica que deseas retirar el vínculo.'],
    [['id_persona' => 'invalid'], 'Selecciona una persona válida.'],
    [['id_persona' => 999], 'La persona no existe o está eliminada.'],
]);

test('deleted persons cannot be linked', function () {
    $admin = personaLinkAdmin();
    $person = Persona::factory()->create();
    $person->delete();
    $target = User::factory()->create();
    $this->actingAs($admin)->put(route('usuarios.persona.update', $target), ['id_persona' => $person->id_persona])
        ->assertSessionHasErrors(['id_persona' => 'La persona no existe o está eliminada.']);
    expect($target->fresh()->id_persona)->toBeNull();
});

test('other roles cannot read or change person links', function (string $code) {
    $user = User::factory()->has(Rol::factory()->state(['codigo' => $code]), 'roles')->create();
    $target = User::factory()->create();
    $person = Persona::factory()->create();
    $this->actingAs($user)->get(route('usuarios.persona.edit', $target))->assertForbidden();
    $this->put(route('usuarios.persona.update', $target), ['id_persona' => $person->id_persona])->assertForbidden();
    expect($target->fresh()->id_persona)->toBeNull();
})->with(['asesor', 'pasante']);

test('guests cannot read or change person links', function () {
    $target = User::factory()->create();
    $this->get(route('usuarios.persona.edit', $target))->assertRedirect(route('login'));
    $this->put(route('usuarios.persona.update', $target), ['id_persona' => null])->assertRedirect(route('login'));
});

test('person search excludes unavailable records and unnecessary personal data', function () {
    $admin = personaLinkAdmin();
    $target = User::factory()->create();
    $match = Persona::factory()->create(['nombre' => 'Buscada', 'ap' => 'Ejemplo', 'curp' => 'TESTPRIVATECURP123']);
    Persona::factory()->create(['nombre' => 'Otra']);
    $taken = Persona::factory()->create(['nombre' => 'Buscada']);
    User::factory()->for($taken, 'persona')->create();
    $deleted = Persona::factory()->create(['nombre' => 'Buscada']);
    $deleted->delete();
    $this->actingAs($admin)->get(route('usuarios.persona.edit', ['user' => $target, 'buscar' => 'Buscada']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('user-persona')->has('personas.data', 1)
            ->where('personas.data.0.id_persona', $match->id_persona)->missing('personas.data.0.curp')->missing('account.password'));
});

test('profile editing cannot change the identity link', function () {
    $person = Persona::factory()->create();
    $other = Persona::factory()->create();
    $user = User::factory()->for($person, 'persona')->create();
    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'id_persona' => $other->id_persona])->assertRedirect();
    expect($user->fresh()->id_persona)->toBe($person->id_persona);
});
