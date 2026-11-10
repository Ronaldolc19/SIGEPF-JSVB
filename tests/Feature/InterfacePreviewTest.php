<?php

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();
});

test('retired interface previews cannot be opened by any role', function (?string $role) {
    if ($role !== null) {
        $user = User::factory()->has(Rol::factory()->state(['codigo' => $role]), 'roles')->create();
        $this->actingAs($user);
    }
    foreach (['administrador', 'asesor', 'pasante'] as $panel) {
        foreach (['', '/personal', '/consulta', '/incidencias', '/productividad', '/academico', '/correspondencia', '/expedientes'] as $module) {
            $this->get('/interfaces/'.$panel.$module)->assertNotFound();
        }
    }
    expect(Route::has('interfaces.show'))->toBeFalse();
    $this->assertDatabaseCount('personas', 0);
})->with([null, 'administrador', 'asesor', 'pasante']);
