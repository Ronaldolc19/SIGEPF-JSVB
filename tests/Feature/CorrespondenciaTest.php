<?php

use App\Models\PersonalFormacion;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CorrespondenciaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 11, 18)->startOfDay());
    Storage::fake('correspondencia');
    Storage::fake('incidencias');
    $this->seed([AccessSeeder::class, CorrespondenciaSeeder::class]);
    $this->personal = PersonalFormacion::factory()->create();
    $this->otherPersonal = PersonalFormacion::factory()->create();
    $this->admin = User::factory()->hasAttached(Rol::where('codigo', 'administrador')->first(), [], 'roles')->create();
    $this->student = User::factory()->hasAttached(Rol::where('codigo', 'pasante')->first(), [], 'roles')->create(['id_persona' => $this->personal->id_persona]);
    $this->other = User::factory()->hasAttached(Rol::where('codigo', 'pasante')->first(), [], 'roles')->create(['id_persona' => $this->otherPersonal->id_persona]);
    $this->payload = ['id_tipo_correspondencia' => DB::table('tipos_correspondencia')->value('id_tipo_correspondencia'), 'direccion' => 'Emitida', 'asunto' => 'Reunión de seguimiento', 'remitente' => 'Área de Enseñanza', 'fecha_documento' => '2026-11-17', 'fecha_movimiento' => '2026-11-18', 'requiere_respuesta' => true, 'fecha_limite' => '2026-11-25', 'responsable_id' => $this->admin->id, 'destinatarios' => [$this->personal->id_personal]];
});

function correspondencePdf(string $name = 'oficio.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
}

function correspondenceDraft(object $test, array $overrides = []): int
{
    $test->actingAs($test->admin)->post(route('correspondencia.store'), array_replace($test->payload, ['archivo' => correspondencePdf()], $overrides))->assertSessionHasNoErrors()->assertRedirect();

    return DB::table('correspondencia')->max('id_correspondencia');
}

function correspondencePublish(object $test, int $id): ?int
{
    $test->actingAs($test->admin)->post(route('correspondencia.publish', $id), ['revision' => 0])->assertSessionHasNoErrors()->assertRedirect();

    return DB::table('destinatarios_correspondencia')->where('id_correspondencia', $id)->value('id_destinatario');
}

test('drafts remain private and publication enables only assigned student access', function () {
    $this->actingAs($this->admin)->get(route('correspondencia.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('correspondencia-form')->has('tipos', 6));
    $id = correspondenceDraft($this);
    $file = DB::table('archivos_correspondencia')->first();
    Storage::disk('correspondencia')->assertExists($file->ruta_archivo);
    $this->actingAs($this->student)->get(route('correspondencia.show', $id))->assertNotFound();
    $this->get(route('correspondencia.download', [$id, $file->id_archivo]))->assertNotFound();
    correspondencePublish($this, $id);
    $this->actingAs($this->student)->get(route('correspondencia.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('correspondencia')->has('documentos.data', 1));
    $this->get(route('correspondencia.show', $id))->assertInertia(fn (Assert $page) => $page->component('correspondencia-detalle')->has('destinatarios', 1)->has('historial', 0));
    expect(DB::table('destinatarios_correspondencia')->value('consultado_at'))->not->toBeNull();
    expect(DB::table('destinatarios_correspondencia')->value('confirmado_at'))->toBeNull();
    $this->get(route('correspondencia.download', [$id, $file->id_archivo]))->assertOk();
    $this->actingAs($this->other)->get(route('correspondencia.show', $id))->assertNotFound();
    $this->get(route('correspondencia.download', [$id, $file->id_archivo]))->assertNotFound();
    $this->get(route('correspondencia.create'))->assertForbidden();
    $this->post(route('correspondencia.store'), $this->payload)->assertForbidden();
    $this->actingAs($this->admin)->put(route('correspondencia.update', $id), $this->payload + ['revision' => 1])->assertForbidden();
    $this->delete(route('correspondencia.destroy', $id), ['revision' => 1])->assertForbidden();
});

test('receipt is explicit idempotent and cannot be confirmed for another recipient', function () {
    $id = correspondenceDraft($this);
    $recipient = correspondencePublish($this, $id);
    $this->actingAs($this->other)->post(route('correspondencia.acknowledge', [$id, $recipient]))->assertNotFound();
    $this->actingAs($this->admin)->post(route('correspondencia.acknowledge', [$id, $recipient]))->assertForbidden();
    $this->actingAs($this->student)->post(route('correspondencia.respond', $id), ['id_destinatario' => $recipient, 'comentario' => 'Respuesta'])->assertSessionHasErrors('comentario');
    $this->post(route('correspondencia.acknowledge', [$id, $recipient]))->assertSessionHasNoErrors();
    $first = DB::table('destinatarios_correspondencia')->first();
    $this->travel(1)->hours();
    $this->post(route('correspondencia.acknowledge', [$id, $recipient]))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('destinatarios_correspondencia', ['id_destinatario' => $recipient, 'confirmado_at' => $first->confirmado_at, 'confirmado_por' => $this->student->id]);
});

test('responses are private within circulars and require review before closing', function () {
    $id = correspondenceDraft($this, ['destinatarios' => [$this->personal->id_personal, $this->otherPersonal->id_personal]]);
    $recipient = correspondencePublish($this, $id);
    $this->actingAs($this->student)->post(route('correspondencia.acknowledge', [$id, $recipient]));
    $reply = ['id_destinatario' => $recipient, 'comentario' => 'Entrego la información solicitada', 'archivo' => correspondencePdf('respuesta.pdf')];
    $this->post(route('correspondencia.respond', $id), $reply)->assertSessionHasNoErrors();
    $response = DB::table('respuestas_correspondencia')->first();
    $this->post(route('correspondencia.respond', $id), $reply)->assertSessionHasErrors('comentario');
    $this->actingAs($this->other)->get(route('correspondencia.show', $id))->assertInertia(fn (Assert $page) => $page->has('respuestas', 0)->has('destinatarios', 1));
    $this->get(route('correspondencia.downloadResponse', [$id, $response->id_respuesta]))->assertNotFound();
    $this->post(route('correspondencia.respond', $id), $reply)->assertNotFound();
    $this->actingAs($this->admin)->post(route('correspondencia.transition', $id), ['estado' => 'Atendida', 'nota' => 'Cierre', 'revision' => 1])->assertSessionHasErrors('estado');
    $this->post(route('correspondencia.review', [$id, $response->id_respuesta]), ['estado' => 'Por corregir', 'revision' => 0])->assertSessionHasErrors('observaciones');
    $this->post(route('correspondencia.review', [$id, $response->id_respuesta]), ['estado' => 'Por corregir', 'observaciones' => 'Completar fecha', 'revision' => 0])->assertSessionHasNoErrors();
    $this->post(route('correspondencia.review', [$id, $response->id_respuesta]), ['estado' => 'Atendida', 'revision' => 0])->assertSessionHasErrors('revision');
    $this->actingAs($this->student)->post(route('correspondencia.respond', $id), ['id_destinatario' => $recipient, 'comentario' => 'Fecha corregida'])->assertSessionHasNoErrors();
    $latest = DB::table('respuestas_correspondencia')->max('id_respuesta');
    $this->actingAs($this->admin)->post(route('correspondencia.review', [$id, $latest]), ['estado' => 'Atendida', 'revision' => 0])->assertSessionHasNoErrors();
    $this->post(route('correspondencia.transition', $id), ['estado' => 'Atendida', 'nota' => 'Cierre', 'revision' => 1])->assertSessionHasErrors('estado');
    $this->post(route('correspondencia.transition', $id), ['estado' => 'Cancelada', 'nota' => 'Se reprogramará la convocatoria', 'revision' => 1])->assertSessionHasNoErrors();
    $this->actingAs($this->student)->post(route('correspondencia.respond', $id), ['id_destinatario' => $recipient, 'comentario' => 'Otra respuesta'])->assertForbidden();
});

test('external correspondence is recorded privately with its response and closure history', function () {
    $id = correspondenceDraft($this, ['direccion' => 'Recibida', 'destinatarios' => [], 'destinatario_externo' => 'Institución educativa']);
    correspondencePublish($this, $id);
    $this->actingAs($this->student)->get(route('correspondencia.show', $id))->assertNotFound();
    $this->actingAs($this->admin)->post(route('correspondencia.respond', $id), ['comentario' => 'Se registró respuesta institucional', 'archivo' => correspondencePdf()])->assertSessionHasNoErrors();
    $this->post(route('correspondencia.transition', $id), ['estado' => 'Atendida', 'nota' => 'Respuesta completa', 'revision' => 1])->assertSessionHasNoErrors();
    $this->post(route('correspondencia.transition', $id), ['estado' => 'En seguimiento', 'nota' => 'Información adicional requerida', 'revision' => 2])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('correspondencia', ['id_correspondencia' => $id, 'estado' => 'En seguimiento', 'revision' => 3]);
    $this->get(route('correspondencia.show', $id))->assertInertia(fn (Assert $page) => $page->has('historial', 5));
});

test('invalid attachments recipients and stale updates leave no partial files or records', function () {
    $this->actingAs($this->admin)->post(route('correspondencia.store'), $this->payload + ['archivo' => UploadedFile::fake()->create('bad.pdf', 10, 'text/plain')])->assertSessionHasErrors('archivo');
    $this->post(route('correspondencia.store'), array_replace($this->payload, ['destinatarios' => [999999], 'archivo' => correspondencePdf()]))->assertSessionHasErrors('destinatarios');
    $this->post(route('correspondencia.store'), array_replace($this->payload, ['destinatarios' => [], 'archivo' => correspondencePdf()]))->assertSessionHasErrors('destinatarios');
    $this->assertDatabaseCount('correspondencia', 0);
    expect(Storage::disk('correspondencia')->allFiles())->toBeEmpty();
    $id = correspondenceDraft($this);
    $this->put(route('correspondencia.update', $id), $this->payload + ['revision' => 0])->assertSessionHasNoErrors();
    $this->put(route('correspondencia.update', $id), $this->payload + ['revision' => 0])->assertSessionHasErrors('revision');
    $this->personal->delete();
    $this->post(route('correspondencia.publish', $id), ['revision' => 1])->assertSessionHasErrors('revision');
    $this->delete(route('correspondencia.destroy', $id), ['revision' => 1])->assertSessionHasNoErrors();
    $this->get(route('correspondencia.show', $id))->assertNotFound();
});

test('removing module permission removes access to previously delivered private files', function () {
    $id = correspondenceDraft($this);
    correspondencePublish($this, $id);
    $file = DB::table('archivos_correspondencia')->first();
    Rol::where('codigo', 'pasante')->first()->permisos()->detach();
    $this->actingAs($this->student->fresh())->get(route('correspondencia.download', [$id, $file->id_archivo]))->assertForbidden();
});

test('incident officios reuse the private file and cannot be broadcast to unrelated recipients', function () {
    $category = DB::table('categorias')->insertGetId(['nombre' => 'Permisos']);
    $type = DB::table('tipos_incidencias')->insertGetId(['nombre' => 'Actividad académica', 'id_categoria' => $category]);
    $incident = DB::table('incidencias')->insertGetId(['id_personal' => $this->personal->id_personal, 'id_tipo_incidencia' => $type, 'fecha_hecho' => '2026-11-17', 'fecha_registro' => '2026-11-17', 'descripcion' => 'Permiso', 'estado' => 'Registrada']);
    Storage::disk('incidencias')->put('oficios/permiso.pdf', "%PDF-1.4\n%%EOF");
    $attachment = DB::table('adjuntos_incidencias')->insertGetId(['id_incidencia' => $incident, 'user_id' => $this->admin->id, 'clase' => 'oficio', 'asunto' => 'Permiso académico', 'nombre_archivo' => 'permiso.pdf', 'ruta_archivo' => 'oficios/permiso.pdf']);
    $linked = $this->payload + ['id_adjunto_incidencia' => $attachment];
    $this->actingAs($this->admin)->post(route('correspondencia.store'), array_replace($linked, ['destinatarios' => [$this->otherPersonal->id_personal]]))->assertSessionHasErrors('id_adjunto_incidencia');
    $this->post(route('correspondencia.store'), $linked)->assertSessionHasNoErrors();
    $id = DB::table('correspondencia')->value('id_correspondencia');
    $file = DB::table('archivos_correspondencia')->first();
    expect($file->ruta_archivo)->toBeNull();
    expect(Storage::disk('correspondencia')->allFiles())->toBeEmpty();
    $this->post(route('correspondencia.store'), $linked)->assertSessionHasErrors('id_adjunto_incidencia');
    $this->assertDatabaseCount('correspondencia', 1);
    correspondencePublish($this, $id);
    $this->actingAs($this->student)->get(route('correspondencia.download', [$id, $file->id_archivo]))->assertDownload('permiso.pdf');
    $this->actingAs($this->other)->get(route('correspondencia.download', [$id, $file->id_archivo]))->assertNotFound();
});

test('calendar narrows administrative correspondence without changing delivery permissions', function () {
    $first = DB::table('promociones')->insertGetId(['nombre' => 'Agosto 2026', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-07-31', 'estado' => 'En curso', 'permite_inscripciones' => false]);
    $second = DB::table('promociones')->insertGetId(['nombre' => 'Febrero 2026', 'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2027-01-31', 'estado' => 'En curso', 'permite_inscripciones' => false]);
    $this->personal->update(['id_promocion' => $first]);
    $this->otherPersonal->update(['id_promocion' => $second]);
    correspondenceDraft($this);
    $otherId = correspondenceDraft($this, ['destinatarios' => [$this->otherPersonal->id_personal]]);
    $this->post(route('promociones.select'), ['promocion' => (string) $second, 'destino' => 'correspondencia.index'])->assertRedirect(route('correspondencia.index'));
    $this->get(route('correspondencia.index'))->assertInertia(fn (Assert $page) => $page->has('documentos.data', 1)->where('documentos.data.0.id_correspondencia', $otherId));
    $this->actingAs($this->student)->get(route('correspondencia.show', $otherId))->assertNotFound();
});

test('an accepted individual response allows closing and preserves the receipt', function () {
    $id = correspondenceDraft($this);
    $recipient = correspondencePublish($this, $id);
    $this->actingAs($this->student)->post(route('correspondencia.acknowledge', [$id, $recipient]))->assertSessionHasNoErrors();
    $this->post(route('correspondencia.respond', $id), ['id_destinatario' => $recipient, 'comentario' => 'Información completa'])->assertSessionHasNoErrors();
    $response = DB::table('respuestas_correspondencia')->value('id_respuesta');
    $this->actingAs($this->admin)->post(route('correspondencia.review', [$id, $response]), ['estado' => 'Atendida', 'revision' => 0])->assertSessionHasNoErrors();
    $this->post(route('correspondencia.transition', $id), ['estado' => 'Atendida', 'nota' => 'Solicitud atendida', 'revision' => 1])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('correspondencia', ['id_correspondencia' => $id, 'estado' => 'Atendida']);
    $this->actingAs($this->student)->get(route('correspondencia.show', $id))->assertOk();
    expect(DB::table('destinatarios_correspondencia')->value('confirmado_at'))->not->toBeNull();
});
