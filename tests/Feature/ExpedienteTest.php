<?php

use App\Models\Permiso;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function expedienteUser(string $role, ?PersonalFormacion $personal = null): User
{
    $rol = Rol::firstOrCreate(['codigo' => $role], ['nombre' => ucfirst($role)]);
    foreach (['ver', 'registrar', 'eliminar', 'revisar'] as $action) {
        $permission = Permiso::firstOrCreate(['codigo' => 'expedientes.'.$action], ['nombre' => $action]);
        $rol->permisos()->syncWithoutDetaching([$permission->id]);
    }

    return User::factory()->hasAttached($rol, [], 'roles')->create(['id_persona' => $personal?->id_persona]);
}

test('trainee uploads multiple private files and admin sees delivery and writes observations', function () {
    Storage::fake('expedientes');
    $personal = PersonalFormacion::factory()->create();
    $trainee = expedienteUser('pasante', $personal);
    $this->actingAs($trainee)->get(route('expedientes.show', $personal->id_personal))->assertInertia(fn (Assert $page) => $page
        ->component('expediente')->has('documentos', 10)->where('documentos.0.nombre', 'Carta de Adscripción y Aceptación')->has('documentos.0.archivos', 0));
    foreach (['constancia.pdf', 'pagina2.pdf'] as $name) {
        $this->post(route('expedientes.store', [$personal->id_personal, 1]), ['archivo' => UploadedFile::fake()->create($name, 30, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertRedirect(route('expedientes.show', $personal->id_personal));
    }
    $this->assertDatabaseCount('documentos_expediente', 1);
    $this->assertDatabaseCount('archivos_expediente', 2);
    $file = DB::table('archivos_expediente')->first();
    Storage::disk('expedientes')->assertExists($file->ruta_archivo);
    $this->actingAs(expedienteUser('administrador'))->get(route('expedientes.index'))->assertInertia(fn (Assert $page) => $page->where('participaciones.data.0.entregados', 1));
    $this->get(route('expedientes.show', $personal->id_personal))->assertInertia(fn (Assert $page) => $page
        ->where('canUpload', false)->where('canReview', true)->has('documentos.0.archivos', 2)->missing('documentos.0.archivos.0.ruta_archivo'));
    $this->get(route('expedientes.download', [$personal->id_personal, $file->id_archivo_expediente]))->assertDownload('constancia.pdf');
    $this->put(route('expedientes.observations', [$personal->id_personal, 1]), ['observaciones' => 'Subir una copia más legible.'])->assertSessionHasNoErrors();
    $this->actingAs($trainee)->get(route('expedientes.show', $personal->id_personal))->assertInertia(fn (Assert $page) => $page->where('documentos.0.observaciones', 'Subir una copia más legible.'));
    $this->put(route('expedientes.observations', [$personal->id_personal, 1]), ['observaciones' => 'Manipulada'])->assertForbidden();
});

test('other trainees cannot list view upload download or remove another persons files', function () {
    Storage::fake('expedientes');
    $personal = PersonalFormacion::factory()->create();
    $other = PersonalFormacion::factory()->create();
    $this->actingAs(expedienteUser('pasante', $personal))->post(route('expedientes.store', [$personal->id_personal, 5]), ['archivo' => UploadedFile::fake()->create('curp.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
    $file = DB::table('archivos_expediente')->first();
    $this->actingAs(expedienteUser('pasante', $other))->get(route('expedientes.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 1)->where('participaciones.data.0.id_personal', $other->id_personal));
    $this->get(route('expedientes.show', $personal->id_personal))->assertNotFound();
    $this->post(route('expedientes.store', [$personal->id_personal, 5]), [])->assertNotFound();
    $this->get(route('expedientes.download', [$personal->id_personal, $file->id_archivo_expediente]))->assertNotFound();
    $this->get(route('expedientes.download', [$other->id_personal, $file->id_archivo_expediente]))->assertNotFound();
    $this->delete(route('expedientes.destroy', [$personal->id_personal, $file->id_archivo_expediente]))->assertNotFound();
    Storage::disk('expedientes')->assertExists($file->ruta_archivo);
});

test('retiring last file returns delivery to no and blocks download while keeping observations', function () {
    Storage::fake('expedientes');
    $personal = PersonalFormacion::factory()->create();
    $user = expedienteUser('pasante', $personal);
    $this->actingAs($user)->post(route('expedientes.store', [$personal->id_personal, 8]), ['archivo' => UploadedFile::fake()->image('curso.png')])->assertSessionHasNoErrors();
    $file = DB::table('archivos_expediente')->first();
    $this->delete(route('expedientes.destroy', [$personal->id_personal, $file->id_archivo_expediente]))->assertRedirect();
    $this->assertSoftDeleted('archivos_expediente', ['id_archivo_expediente' => $file->id_archivo_expediente]);
    $this->get(route('expedientes.download', [$personal->id_personal, $file->id_archivo_expediente]))->assertNotFound();
    $this->get(route('expedientes.index'))->assertInertia(fn (Assert $page) => $page->where('participaciones.data.0.entregados', 0));
    Storage::disk('expedientes')->assertExists($file->ruta_archivo);
});

test('unsafe missing and oversized files are rejected without writing files', function (string $case) {
    Storage::fake('expedientes');
    $personal = PersonalFormacion::factory()->create();
    $content = UploadedFile::fake()->createWithContent('payload.php', '<?php echo "test";');
    $file = match ($case) {
        'oversized' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
        'extension' => UploadedFile::fake()->create('document.php', 10, 'application/pdf'),
        'spoofed' => new UploadedFile($content->getPathname(), 'document.pdf', null, null, true),
        default => null,
    };
    $this->actingAs(expedienteUser('pasante', $personal))->post(route('expedientes.store', [$personal->id_personal, 1]), ['archivo' => $file])->assertSessionHasErrors('archivo');
    $this->assertDatabaseCount('archivos_expediente', 0);
    expect(Storage::disk('expedientes')->allFiles())->toBe([]);
})->with(['missing', 'oversized', 'extension', 'spoofed']);

test('admin cannot upload or retire files and advisor cannot access expediente even with stored grants', function () {
    $personal = PersonalFormacion::factory()->create();
    $this->actingAs(expedienteUser('administrador'))->post(route('expedientes.store', [$personal->id_personal, 1]), [])->assertForbidden();
    $this->delete(route('expedientes.destroy', [$personal->id_personal, 1]))->assertForbidden();
    $this->actingAs(expedienteUser('asesor'))->get(route('expedientes.index'))->assertForbidden();
    $this->get(route('expedientes.download', [$personal->id_personal, 1]))->assertForbidden();
});

test('unlinked accounts have clear empty state and revoking module blocks access immediately', function () {
    $user = expedienteUser('pasante');
    $this->actingAs($user)->get(route('expedientes.index'))->assertInertia(fn (Assert $page) => $page->has('participaciones.data', 0));
    $user->roles()->first()->permisos()->detach();
    $this->get(route('expedientes.index'))->assertForbidden();
    $this->post(route('expedientes.store', [1, 1]), [])->assertForbidden();
});

test('guest must authenticate before viewing or downloading expediente', function () {
    $this->get(route('expedientes.index'))->assertRedirect(route('login'));
    $this->get(route('expedientes.download', [1, 1]))->assertRedirect(route('login'));
});

test('database failure after upload removes the uncommitted private file', function () {
    Storage::fake('expedientes');
    $personal = PersonalFormacion::factory()->create();
    DB::unprepared("CREATE TRIGGER fail_expediente BEFORE INSERT ON archivos_expediente BEGIN SELECT RAISE(ABORT, 'test failure'); END;");
    try {
        $this->actingAs(expedienteUser('pasante', $personal))->post(route('expedientes.store', [$personal->id_personal, 1]), ['archivo' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')])->assertServerError();
        $this->assertDatabaseCount('documentos_expediente', 0);
        expect(Storage::disk('expedientes')->allFiles())->toBe([]);
    } finally {
        DB::unprepared('DROP TRIGGER fail_expediente');
    }
});
