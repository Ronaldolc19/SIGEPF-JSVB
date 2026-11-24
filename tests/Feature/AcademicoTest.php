<?php

use App\Models\Asesor;
use App\Models\AsignacionAsesor;
use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\AcademicoAccessSeeder;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('academico');
    $this->seed([AccessSeeder::class, AcademicoAccessSeeder::class]);
    $this->personal = PersonalFormacion::factory()->create();
    $this->student = User::factory()->hasAttached(Rol::where('codigo', 'pasante')->first(), [], 'roles')->create(['id_persona' => $this->personal->id_persona]);
    $this->admin = User::factory()->hasAttached(Rol::where('codigo', 'administrador')->first(), [], 'roles')->create();
    $this->type = DB::table('tipos_documentos')->insertGetId(['nombre' => 'Trabajo académico de prueba']);
    $this->payload = ['id_personal' => $this->personal->id_personal, 'id_tipo_documento' => $this->type, 'titulo' => 'Trabajo de promoción de la salud'];
});

function academicPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('trabajo.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
}

function academicDelivery(object $test): int
{
    $test->actingAs($test->student)->post(route('academico.store'), $test->payload + ['archivo' => academicPdf()])->assertSessionHasNoErrors()->assertRedirect();

    return DB::table('documentos_investigacion')->max('id_documento');
}

test('trainee delivers private pdf and the administrator sees the latest submission', function () {
    $this->actingAs($this->student)->get(route('academico.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('academico-form')->has('personal', 1)->has('tipos', 1));
    $id = academicDelivery($this);
    $row = DB::table('documentos_investigacion')->first();
    Storage::disk('academico')->assertExists($row->ruta_archivo);
    $this->assertDatabaseHas('documentos_investigacion', ['id_documento' => $id, 'subido_por' => $this->student->id, 'version' => 1, 'estado' => 'Pendiente de revisión']);
    $this->get(route('academico.show', $id))->assertInertia(fn (Assert $page) => $page->component('academico-detalle')->has('versiones', 1)->where('canReview', false)->where('canUpload', false)->missing('documento.ruta_archivo')->missing('versiones.0.ruta_archivo'));
    $this->get(route('academico.download', $id))->assertDownload('trabajo.pdf');
    $this->actingAs($this->admin)->get(route('academico.index'))->assertInertia(fn (Assert $page) => $page->component('academico')->has('documentos.data', 1)->where('canUpload', false));
    $this->get(route('academico.show', $id))->assertInertia(fn (Assert $page) => $page->where('canReview', true));
    $this->post(route('academico.store'), $this->payload + ['archivo' => academicPdf()])->assertForbidden();
});

test('correction creates a new version preserves files and observations and approval locks delivery', function () {
    $id = academicDelivery($this);
    $this->actingAs($this->admin)->post(route('academico.review', $id), ['estado' => 'Por corregir', 'observacion' => 'Completar los resultados.', 'revision' => 0])->assertSessionHasNoErrors();
    $this->actingAs($this->student)->get(route('academico.show', $id))->assertInertia(fn (Assert $page) => $page->where('canUpload', true)->where('observaciones.0.observacion', 'Completar los resultados.'));
    $correction = $this->payload + ['id_documento_anterior' => $id, 'revision' => 1];
    $this->post(route('academico.store'), $correction + ['archivo' => academicPdf()])->assertSessionHasNoErrors();
    $second = DB::table('documentos_investigacion')->max('id_documento');
    $this->assertDatabaseHas('documentos_investigacion', ['id_documento' => $second, 'id_documento_anterior' => $id, 'version' => 2, 'estado' => 'Pendiente de revisión']);
    $this->post(route('academico.store'), $correction + ['archivo' => academicPdf()])->assertSessionHasErrors('archivo');
    expect(Storage::disk('academico')->allFiles())->toHaveCount(2);
    $this->get(route('academico.show', $id))->assertInertia(fn (Assert $page) => $page->where('documento.id_documento', $second)->has('versiones', 2)->has('observaciones', 1));
    $this->get(route('academico.index'))->assertInertia(fn (Assert $page) => $page->has('documentos.data', 1)->where('documentos.data.0.version', 2));
    $this->get(route('academico.download', $id))->assertOk();
    $this->actingAs($this->admin)->post(route('academico.review', $second), ['estado' => 'Aprobado', 'observacion' => 'Correcciones atendidas.', 'revision' => 0])->assertSessionHasNoErrors();
    $this->actingAs($this->student)->post(route('academico.store'), $this->payload + ['id_documento_anterior' => $second, 'revision' => 1, 'archivo' => academicPdf()])->assertSessionHasErrors('archivo');
    $this->get(route('academico.show', $second))->assertInertia(fn (Assert $page) => $page->where('documento.estado', 'Aprobado')->where('canUpload', false)->has('observaciones', 2));
});

test('another trainee cannot read download correct or review someone elses document', function () {
    $id = academicDelivery($this);
    $otherPerson = PersonalFormacion::factory()->create();
    $other = User::factory()->hasAttached(Rol::where('codigo', 'pasante')->first(), [], 'roles')->create(['id_persona' => $otherPerson->id_persona]);
    $this->actingAs($other)->get(route('academico.index'))->assertInertia(fn (Assert $page) => $page->has('documentos.data', 0));
    $this->get(route('academico.show', $id))->assertNotFound();
    $this->get(route('academico.download', $id))->assertNotFound();
    $this->post(route('academico.store'), $this->payload + ['archivo' => academicPdf()])->assertNotFound();
    $this->post(route('academico.store'), array_replace($this->payload, ['id_personal' => $otherPerson->id_personal, 'id_documento_anterior' => $id, 'revision' => 0, 'archivo' => academicPdf()]))->assertNotFound();
    $this->post(route('academico.review', $id), ['estado' => 'Aprobado', 'observacion' => 'Intento', 'revision' => 0])->assertForbidden();
    expect(Storage::disk('academico')->allFiles())->toHaveCount(1);
});

test('advisor access and review follow current assignments and preserve reviewer identity', function () {
    $id = academicDelivery($this);
    $advisor = Asesor::factory()->create();
    $account = User::factory()->hasAttached(Rol::where('codigo', 'asesor')->first(), [], 'roles')->create(['id_persona' => $advisor->id_persona]);
    $this->actingAs($account)->get(route('academico.show', $id))->assertNotFound();
    $assignment = AsignacionAsesor::factory()->create(['id_personal' => $this->personal->id_personal, 'id_asesor' => $advisor->id_asesor]);
    $this->get(route('academico.show', $id))->assertInertia(fn (Assert $page) => $page->where('canReview', true));
    $this->post(route('academico.review', $id), ['estado' => 'Por corregir', 'observacion' => 'Precisar objetivos.', 'revision' => 0])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('observaciones_documentos', ['id_documento' => $id, 'user_id' => $account->id, 'id_asesor' => $advisor->id_asesor]);
    $assignment->update(['fecha_fin' => today()->subDay()->toDateString()]);
    $this->get(route('academico.show', $id))->assertNotFound();
    $this->get(route('academico.download', $id))->assertNotFound();
    $this->post(route('academico.review', $id), ['estado' => 'Aprobado', 'observacion' => 'Intento', 'revision' => 1])->assertNotFound();
});

test('invalid files duplicate initial submissions and unavailable types do not create orphan files', function () {
    $this->actingAs($this->student)->post(route('academico.store'), $this->payload + ['archivo' => UploadedFile::fake()->create('bad.pdf', 1, 'text/plain')])->assertSessionHasErrors('archivo');
    $this->post(route('academico.store'), $this->payload + ['archivo' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('archivo');
    DB::table('tipos_documentos')->where('id_tipo_documento', $this->type)->update(['deleted_at' => now()]);
    $this->post(route('academico.store'), $this->payload + ['archivo' => academicPdf()])->assertSessionHasErrors('id_tipo_documento');
    expect(Storage::disk('academico')->allFiles())->toBeEmpty();
    DB::table('tipos_documentos')->where('id_tipo_documento', $this->type)->update(['deleted_at' => null]);
    $id = academicDelivery($this);
    $this->post(route('academico.store'), $this->payload + ['archivo' => academicPdf()])->assertSessionHasErrors('id_tipo_documento');
    $this->post(route('academico.store'), $this->payload + ['archivo' => academicPdf(), 'id_documento_anterior' => $id, 'revision' => 0])->assertSessionHasErrors('archivo');
    expect(Storage::disk('academico')->allFiles())->toHaveCount(1);
});

test('review requires observations and cannot overwrite an existing decision or approve oneself', function () {
    $id = academicDelivery($this);
    $this->actingAs($this->admin)->post(route('academico.review', $id), ['estado' => 'Por corregir', 'revision' => 0])->assertSessionHasErrors('observacion');
    $this->post(route('academico.review', $id), ['estado' => 'Aprobado', 'observacion' => 'Conforme', 'revision' => 99])->assertSessionHasErrors('revision');
    $this->post(route('academico.review', $id), ['estado' => 'Aprobado', 'observacion' => 'Conforme', 'revision' => 0])->assertSessionHasNoErrors();
    $this->post(route('academico.review', $id), ['estado' => 'Por corregir', 'observacion' => 'Cambiar', 'revision' => 1])->assertSessionHasErrors('revision');
    $this->assertDatabaseCount('observaciones_documentos', 1);
    $this->student->roles()->attach(Rol::where('codigo', 'administrador')->first());
    $this->actingAs($this->student->fresh())->post(route('academico.review', $id), ['estado' => 'Aprobado', 'observacion' => 'Propio', 'revision' => 1])->assertForbidden();
});

test('module permissions and missing personal linkage protect the repository', function () {
    $this->get(route('academico.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('academico.index'))->assertForbidden();
    $id = academicDelivery($this);
    $this->student->forceFill(['id_persona' => null])->save();
    $this->actingAs($this->student->fresh())->get(route('academico.download', $id))->assertNotFound();
    Rol::where('codigo', 'pasante')->first()->permisos()->detach();
    $this->actingAs($this->student->fresh())->get(route('academico.index'))->assertForbidden();
});

test('academic delivery resolves the logged in trainee period from the selected promotion', function () {
    $promotion = DB::table('promociones')->insertGetId(['nombre' => 'Periodo anterior', 'fecha_inicio' => '2025-08-01', 'fecha_fin' => '2026-07-31', 'estado' => 'Cerrada']);
    $older = PersonalFormacion::factory()->create(['id_persona' => $this->personal->id_persona, 'id_promocion' => $promotion, 'fecha_ingreso' => '2025-08-01']);
    $this->actingAs($this->student)->post(route('promociones.select'), ['promocion' => 'sin', 'destino' => 'academico.index']);
    $this->get(route('academico.create'))->assertInertia(fn (Assert $page) => $page->has('personal', 1)->where('personal.0.id_personal', $this->personal->id_personal));
    $this->post(route('promociones.select'), ['promocion' => (string) $promotion, 'destino' => 'academico.index']);
    $this->get(route('academico.create'))->assertInertia(fn (Assert $page) => $page->has('personal', 1)->where('personal.0.id_personal', $older->id_personal));
});

test('calendar and state filters narrow repository results while previous versions remain downloadable', function () {
    $promotion = DB::table('promociones')->insertGetId(['nombre' => 'Promoción de prueba', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-07-31', 'estado' => 'En curso']);
    $this->personal->update(['id_promocion' => $promotion]);
    $id = academicDelivery($this);
    $this->actingAs($this->admin)->post(route('promociones.select'), ['promocion' => 'sin', 'destino' => 'academico.index'])->assertRedirect(route('academico.index'));
    $this->get(route('academico.index'))->assertInertia(fn (Assert $page) => $page->has('documentos.data', 0));
    $this->post(route('promociones.select'), ['promocion' => (string) $promotion, 'destino' => 'academico.index'])->assertRedirect(route('academico.index'));
    $this->get(route('academico.index', ['buscar' => 'Trabajo promoción', 'estado' => 'Pendiente de revisión']))->assertInertia(fn (Assert $page) => $page->has('documentos.data', 1));
    $this->get(route('academico.index', ['estado' => 'Aprobado']))->assertInertia(fn (Assert $page) => $page->has('documentos.data', 0));
    $this->get(route('academico.download', $id))->assertOk();
});
