<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\RolSeeder;

test('users keep multiple roles isolated from other users with assignment timestamps', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $roles = Rol::factory()->count(2)->create();
    $otherRole = Rol::factory()->create();
    $otherUser->roles()->attach($otherRole);

    $user->roles()->attach($roles);

    expect($user->fresh()->roles->modelKeys())->toEqualCanonicalizing($roles->modelKeys());
    expect($otherUser->fresh()->roles->modelKeys())->toBe([$otherRole->id]);
    expect($roles->first()->usuarios->modelKeys())->toBe([$user->id]);
    expect($user->roles->first()->pivot->created_at)->not->toBeNull();
    expect($user->roles->first()->pivot->updated_at)->not->toBeNull();
});

test('permissions can be shared by roles without including unrelated grants', function () {
    $roles = Rol::factory()->count(2)->create();
    $permission = Permiso::factory()->create();
    $otherPermission = Permiso::factory()->create();
    $roles->last()->permisos()->attach($otherPermission);

    $permission->roles()->attach($roles);

    expect($roles->first()->permisos->modelKeys())->toBe([$permission->id]);
    expect($roles->last()->permisos->modelKeys())->toEqualCanonicalizing([$permission->id, $otherPermission->id]);
    expect($permission->fresh()->roles->modelKeys())->toEqualCanonicalizing($roles->modelKeys());
    expect($roles->first()->permisos->first()->pivot->created_at)->not->toBeNull();
    expect($roles->first()->permisos->first()->pivot->updated_at)->not->toBeNull();
});

test('removing a role assignment preserves the role and other user assignments', function () {
    $role = Rol::factory()->create();
    $users = User::factory()->count(2)->create();
    $role->usuarios()->attach($users);

    $users->first()->roles()->detach($role);

    expect($users->first()->fresh()->roles)->toBeEmpty();
    expect($users->last()->fresh()->roles->modelKeys())->toBe([$role->id]);
    $this->assertModelExists($role);
});

test('role seeding is repeatable and does not grant roles to users', function () {
    $user = User::factory()->create();
    $this->seed(RolSeeder::class);

    $this->seed(RolSeeder::class);

    expect(Rol::orderBy('codigo')->pluck('codigo')->all())->toBe(['administrador', 'asesor', 'pasante']);
    expect($user->fresh()->roles)->toBeEmpty();
});
