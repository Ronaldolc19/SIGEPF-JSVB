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

function consultaUser(string $role, ?int $person = null): User
{
    $rol = Rol::firstOrCreate(['codigo' => $role], ['nombre' => ucfirst($role)]);
    $permission = Permiso::firstOrCreate(['codigo' => 'consulta.ver'], ['nombre' => 'Ver consulta']);
    $rol->permisos()->syncWithoutDetaching([$permission->id]);

    return User::factory()->hasAttached($rol, [], 'roles')->create(['id_persona' => $person]);
}

function consultaPayload(): array
{
    return ['titulo' => 'Guía del informe mensual', 'descripcion' => 'Instrucciones de Enseñanza.', 'estado' => 'Borrador',
        'id_tipo_documento' => DB::table('tipos_documentos')->insertGetId(['nombre' => fake()->unique()->uuid()]), 'id_carrera' => null, 'id_tipo_personal' => null];
}

function consultaMaterial(array $attributes = []): int
{
    return DB::table('materiales_consultas')->insertGetId([...consultaPayload(), 'estado' => 'Publicado', 'fecha_publicacion' => now(),
        'nombre_archivo' => 'guia.pdf', 'ruta_archivo' => 'materiales/guia.pdf', 'created_at' => now(), 'updated_at' => now(), ...$attributes], 'id_material');
}

test('admin uploads publishes edits and retires material and trainees only download publications', function () {
    Storage::fake('consulta');
    $data = consultaPayload();
    $personal = PersonalFormacion::factory()->create();
    $trainee = consultaUser('pasante', $personal->id_persona);
    $admin = consultaUser('administrador');
    $this->actingAs($admin)->post(route('consulta.store'), [...$data, 'archivo' => UploadedFile::fake()->create('guia.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
    $material = DB::table('materiales_consultas')->first();
    Storage::disk('consulta')->assertExists($material->ruta_archivo);
    $this->actingAs($trainee)->get(route('consulta.download', $material->id_material))->assertNotFound();
    $this->get(route('consulta.index'))->assertInertia(fn (Assert $page) => $page->has('materiales.data', 0));
    $this->actingAs($admin)->put(route('consulta.update', $material->id_material), [...$data, 'estado' => 'Publicado', 'revision' => 0])->assertSessionHasNoErrors();
    $this->actingAs($trainee)->get(route('consulta.index'))->assertInertia(fn (Assert $page) => $page->has('materiales.data', 1)->missing('materiales.data.0.ruta_archivo'));
    $this->get(route('consulta.download', $material->id_material))->assertDownload('guia.pdf');
    $this->actingAs($admin)->post(route('consulta.update', $material->id_material), [...$data, '_method' => 'PUT', 'estado' => 'Publicado', 'revision' => 1, 'archivo' => UploadedFile::fake()->create('actualizada.pdf', 30, 'application/pdf')])->assertSessionHasNoErrors();
    Storage::disk('consulta')->assertMissing($material->ruta_archivo);
    $replacement = DB::table('materiales_consultas')->first();
    Storage::disk('consulta')->assertExists($replacement->ruta_archivo);
    $this->put(route('consulta.update', $material->id_material), [...$data, 'estado' => 'Retirado', 'revision' => 2])->assertSessionHasNoErrors();
    $this->actingAs($trainee)->get(route('consulta.download', $material->id_material))->assertNotFound();
    $this->get(route('consulta.index', ['estado' => 'Retirado']))->assertInertia(fn (Assert $page) => $page->has('materiales.data', 0));
});

test('audiences match both dimensions within the same participation and exclude archived profiles and future publications', function () {
    Storage::fake('consulta');
    Storage::disk('consulta')->put('materiales/guia.pdf', '%PDF-1.4 test');
    $one = PersonalFormacion::factory()->create();
    $two = PersonalFormacion::factory()->create(['id_persona' => $one->id_persona]);
    $careerOne = DB::table('carreras')->insertGetId(['nombre' => 'Medicina']);
    $careerTwo = DB::table('carreras')->insertGetId(['nombre' => 'Enfermería']);
    $institution = DB::table('instituciones')->insertGetId(['nombre' => 'Institución prueba']);
    foreach ([[$one, $careerOne], [$two, $careerTwo]] as [$personal, $career]) {
        DB::table('datos_academicos')->insert(['id_personal' => $personal->id_personal, 'id_institucion' => $institution, 'id_carrera' => $career]);
    }
    $general = consultaMaterial();
    $matching = consultaMaterial(['id_carrera' => $careerOne, 'id_tipo_personal' => $one->id_tipo_personal]);
    $careerOnly = consultaMaterial(['id_carrera' => $careerTwo]);
    $crossed = consultaMaterial(['id_carrera' => $careerOne, 'id_tipo_personal' => $two->id_tipo_personal]);
    $future = consultaMaterial(['fecha_publicacion' => now()->addDay()]);
    $this->actingAs(consultaUser('pasante', $one->id_persona))->get(route('consulta.index'))->assertInertia(fn (Assert $page) => $page->has('materiales.data', 3));
    foreach ([$general, $matching, $careerOnly] as $id) {
        $this->get(route('consulta.download', $id))->assertOk();
    }
    foreach ([$crossed, $future] as $id) {
        $this->get(route('consulta.download', $id))->assertNotFound();
    }
    $one->delete();
    $this->get(route('consulta.download', $matching))->assertNotFound();
    $two->persona->delete();
    $this->get(route('consulta.download', $general))->assertNotFound();
    Storage::disk('consulta')->assertExists('materiales/guia.pdf');
});

test('unlinked students see guidance and cannot download even general materials', function () {
    $id = consultaMaterial();
    $this->actingAs(consultaUser('pasante'))->get(route('consulta.index'))->assertInertia(fn (Assert $page) => $page->where('linked', false)->has('materiales.data', 0));
    $this->get(route('consulta.download', $id))->assertNotFound();
});

test('role and module restrictions protect administration and reading', function () {
    $this->get(route('consulta.index'))->assertRedirect(route('login'));
    $this->actingAs(consultaUser('asesor'))->get(route('consulta.index'))->assertForbidden();
    $user = consultaUser('pasante');
    $this->actingAs($user)->get(route('consulta.create'))->assertForbidden();
    $this->post(route('consulta.store'), [])->assertForbidden();
    $this->put(route('consulta.update', 1), [])->assertForbidden();
    $this->delete(route('consulta.destroy', 1))->assertForbidden();
    $user->roles()->first()->permisos()->detach();
    $this->get(route('consulta.index'))->assertForbidden();
    $this->get(route('consulta.download', 1))->assertForbidden();
});

test('unsafe and oversized uploads and missing files are rejected without storage writes', function (string $case) {
    Storage::fake('consulta');
    $content = UploadedFile::fake()->createWithContent('payload.php', '<?php echo "test";');
    $file = match ($case) {
        'oversized' => UploadedFile::fake()->create('grande.pdf', 20481, 'application/pdf'),
        'extension' => UploadedFile::fake()->create('guia.php', 20, 'application/pdf'),
        'spoofed' => new UploadedFile($content->getPathname(), 'guia.pdf', null, null, true),
        default => null,
    };
    $this->actingAs(consultaUser('administrador'))->post(route('consulta.store'), [...consultaPayload(), 'archivo' => $file])->assertSessionHasErrors('archivo');
    $this->assertDatabaseCount('materiales_consultas', 0);
    expect(Storage::disk('consulta')->allFiles())->toBe([]);
})->with(['missing', 'oversized', 'extension', 'spoofed']);

test('stale edits clean their new file and deletion blocks previously authorized downloads', function () {
    Storage::fake('consulta');
    Storage::disk('consulta')->put('materiales/guia.pdf', '%PDF-1.4 test');
    $id = consultaMaterial(['revision' => 2]);
    $row = DB::table('materiales_consultas')->first();
    $data = ['titulo' => $row->titulo, 'id_tipo_documento' => $row->id_tipo_documento, 'estado' => 'Publicado', 'revision' => 1, '_method' => 'PUT'];
    $admin = consultaUser('administrador');
    $this->actingAs($admin)->post(route('consulta.update', $id), [...$data, 'archivo' => UploadedFile::fake()->create('nuevo.pdf', 30, 'application/pdf')])->assertSessionHasErrors('revision');
    expect(Storage::disk('consulta')->allFiles())->toBe(['materiales/guia.pdf']);
    $this->delete(route('consulta.destroy', $id))->assertRedirect();
    $this->assertSoftDeleted('materiales_consultas', ['id_material' => $id]);
    $this->get(route('consulta.download', $id))->assertNotFound();
});

test('invalid catalogs and missing stored files cannot be published', function () {
    Storage::fake('consulta');
    $id = consultaMaterial(['estado' => 'Borrador', 'fecha_publicacion' => null]);
    $row = DB::table('materiales_consultas')->first();
    $data = ['titulo' => $row->titulo, 'id_tipo_documento' => $row->id_tipo_documento, 'estado' => 'Publicado', 'revision' => 0];
    $this->actingAs(consultaUser('administrador'))->put(route('consulta.update', $id), $data)->assertSessionHasErrors('archivo');
    DB::table('tipos_documentos')->where('id_tipo_documento', $row->id_tipo_documento)->update(['deleted_at' => now()]);
    $this->put(route('consulta.update', $id), $data)->assertSessionHasErrors('id_tipo_documento');
    $this->assertDatabaseHas('materiales_consultas', ['id_material' => $id, 'estado' => 'Borrador']);
    expect(Storage::disk('consulta')->allFiles())->toBe([]);
});
