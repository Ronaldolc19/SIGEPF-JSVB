<?php

use App\Models\Rol;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('home presents the working login with password recovery', function () {
    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('canResetPassword', true));
});

test('authenticated visitors to home continue to their dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('home'))->assertRedirect(route('dashboard'));
});

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('login generates secure links behind the hosting proxy', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
        ->get('http://sigepf.example.test/login')
        ->assertOk()
        ->assertSee('https://sigepf.example.test', false)
        ->assertDontSee('http://sigepf.example.test', false);
});

test('login remains accessible over local http without a proxy', function () {
    $this->get('http://localhost/login')
        ->assertOk()
        ->assertSee('http://localhost', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('administrator can log in with an internationalized email address', function () {
    $user = User::factory()->create(['email' => 'admin_enseñanza@example.com']);
    $user->roles()->attach(Rol::factory()->create(['codigo' => 'administrador']));

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))->assertRedirect(route('administrador.dashboard'));
});

test('login displays validation errors for empty fields', function () {
    $this->post('/login', [])->assertSessionHasErrors([
        'email' => 'Ingresa tu correo electrónico.',
        'password' => 'Ingresa tu contraseña.',
    ]);
    $this->assertGuest();
});

test('login rejects malformed email addresses on the server', function () {
    $this->post('/login', ['email' => 'correo-invalido', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Ingresa un correo electrónico válido.']);
    $this->assertGuest();
});

test('incorrect credentials display a clear error without identifying the account', function () {
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);
    $this->assertGuest();
});
