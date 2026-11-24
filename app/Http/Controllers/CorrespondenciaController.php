<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCorrespondenciaRequest;
use App\Models\PersonalFormacion;
use App\Models\User;
use App\Promociones;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CorrespondenciaController extends Controller
{
    private function recipients(User $user): Builder
    {
        $query = DB::table('destinatarios_correspondencia as d')->join('personal_formacion as pf', 'pf.id_personal', '=', 'd.id_personal')
            ->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')->whereNull('pf.deleted_at')->whereNull('p.deleted_at');
        if (! $user->hasRole('administrador')) {
            $query->where('pf.id_persona', $user->hasRole('pasante') ? ($user->id_persona ?? 0) : 0);
        }

        return $query;
    }

    private function visible(User $user): Builder
    {
        $query = DB::table('correspondencia as c')->whereNull('c.deleted_at');
        if (! $user->hasRole('administrador')) {
            $query->whereNotNull('c.publicado_at')->where('c.estado', '!=', 'Borrador')
                ->whereIn('c.id_correspondencia', $this->recipients($user)->select('d.id_correspondencia'));
        }

        return $query;
    }

    private function history(int $id, User $user, string $event, ?string $note = null): void
    {
        DB::table('historial_correspondencia')->insert(['id_correspondencia' => $id, 'user_id' => $user->id, 'evento' => $event, 'nota' => $note, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function revision(object $document, int $revision): void
    {
        if ((int) $document->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => 'Otro usuario modificó el documento. Recarga la página antes de continuar.']);
        }
    }

    public function index(Request $request): Response
    {
        $data = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', Rule::in(['Borrador', 'En seguimiento', 'Atendida', 'Cancelada'])], 'vencidas' => ['nullable', 'boolean']]);
        $user = $request->user();
        $query = $this->visible($user);
        $recipients = $this->recipients($user)->select('d.id_correspondencia');
        Promociones::filter($request, $recipients, 'pf.id_promocion');
        $query->where(function (Builder $scope) use ($recipients, $user): void {
            $scope->whereIn('c.id_correspondencia', $recipients);
            if ($user->hasRole('administrador')) {
                $scope->orWhereNotExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('destinatarios_correspondencia as d')->whereColumn('d.id_correspondencia', 'c.id_correspondencia'));
            }
        });
        if (! empty($data['buscar'])) {
            $query->where(fn (Builder $q): Builder => $q->where('c.asunto', 'like', '%'.$data['buscar'].'%')->orWhere('c.numero_oficio', 'like', '%'.$data['buscar'].'%')->orWhere('c.remitente', 'like', '%'.$data['buscar'].'%'));
        }
        if (! empty($data['estado'])) {
            $query->where('c.estado', $data['estado']);
        }
        if (! empty($data['vencidas'])) {
            $query->where('c.estado', 'En seguimiento')->where('c.requiere_respuesta', true)->where('c.fecha_limite', '<', today()->toDateString());
        }

        return Inertia::render('correspondencia', [
            'documentos' => $query->join('tipos_correspondencia as t', 't.id_tipo_correspondencia', '=', 'c.id_tipo_correspondencia')
                ->select('c.id_correspondencia', 'c.asunto', 'c.numero_oficio', 'c.remitente', 'c.direccion', 'c.fecha_documento', 'c.fecha_limite', 'c.estado', 'c.requiere_respuesta', 't.nombre as tipo')
                ->orderByDesc('c.id_correspondencia')->paginate(15)->withQueryString(),
            'filters' => $data, 'isAdmin' => $user->hasRole('administrador'), 'hoy' => today()->toDateString(),
        ]);
    }

    public function form(Request $request, ?int $id_correspondencia = null): Response
    {
        $item = $id_correspondencia ? $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->first() : null;
        abort_if($id_correspondencia && ! $item, 404);
        abort_if($item && $item->estado !== 'Borrador', 403);

        return Inertia::render('correspondencia-form', [
            'documento' => $item,
            'seleccionados' => $item ? DB::table('destinatarios_correspondencia')->where('id_correspondencia', $id_correspondencia)->pluck('id_personal') : [],
            'principal' => $item ? DB::table('archivos_correspondencia')->where('id_correspondencia', $id_correspondencia)->where('clase', 'Principal')->first(['nombre_archivo', 'id_adjunto_incidencia']) : null,
            'tipos' => DB::table('tipos_correspondencia')->whereNull('deleted_at')->orderBy('nombre')->get(['id_tipo_correspondencia', 'nombre']),
            'responsables' => User::query()->whereHas('roles', fn (EloquentBuilder $q): EloquentBuilder => $q->where('codigo', 'administrador'))->orderBy('name')->get(['id', 'name']),
            'responsableDefault' => $request->user()->id,
            'personas' => DB::table('personal_formacion as pf')->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')
                ->leftJoin('datos_academicos as da', function (JoinClause $join): void {
                    $join->on('da.id_personal', '=', 'pf.id_personal')->whereNull('da.deleted_at');
                })
                ->leftJoin('carreras as ca', 'ca.id_carrera', '=', 'da.id_carrera')->leftJoin('promociones as pr', 'pr.id_promocion', '=', 'pf.id_promocion')
                ->whereNull('pf.deleted_at')->whereNull('p.deleted_at')->orderBy('p.ap')
                ->get(['pf.id_personal', 'p.nombre', 'p.ap', 'p.am', 'pf.id_promocion', 'da.id_carrera', 'ca.nombre as carrera', 'pr.nombre as promocion']),
            'adscripciones' => DB::table('adscripciones_personal as a')->join('unidades_medicas as u', 'u.id_unidad', '=', 'a.id_unidad')
                ->whereNull('a.deleted_at')->where('a.fecha_inicio', '<=', today()->toDateString())
                ->where(fn (Builder $q): Builder => $q->whereNull('a.fecha_fin')->orWhere('a.fecha_fin', '>=', today()->toDateString()))->get(['a.id_personal', 'a.id_unidad', 'u.nom_unidad']),
            'oficios' => DB::table('adjuntos_incidencias as a')->join('incidencias as i', 'i.id_incidencia', '=', 'a.id_incidencia')
                ->where('a.clase', 'oficio')->whereNull('i.deleted_at')->whereNotNull('a.ruta_archivo')
                ->orderByDesc('a.id_adjunto')->get(['a.id_adjunto', 'i.id_personal', 'a.numero_oficio', 'a.asunto', 'a.fecha_oficio']),
            'hoy' => today()->toDateString(),
        ]);
    }

    /** @return array{nombre_archivo: string, ruta_archivo: string} */
    private function upload(UploadedFile $file): array
    {
        return ['nombre_archivo' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'ruta_archivo' => $file->store('documentos', 'correspondencia')];
    }

    public function save(SaveCorrespondenciaRequest $request, ?int $id_correspondencia = null): RedirectResponse
    {
        $data = $request->validated();
        $recipients = array_map('intval', $data['destinatarios']);
        if (! $recipients && empty($data['destinatario_externo'])) {
            throw ValidationException::withMessages(['destinatarios' => 'Selecciona destinatarios o registra la contraparte externa.']);
        }
        if ($request->hasFile('archivo') && ! empty($data['id_adjunto_incidencia'])) {
            throw ValidationException::withMessages(['archivo' => 'Adjunta un PDF o vincula un oficio existente; elige una sola opción.']);
        }
        $newFiles = [];
        $obsolete = null;
        try {
            $id = DB::transaction(function () use ($request, $data, $recipients, $id_correspondencia, &$newFiles, &$obsolete): int {
                $current = $id_correspondencia ? $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->lockForUpdate()->first() : null;
                abort_if($id_correspondencia && ! $current, 404);
                if ($current) {
                    abort_unless($current->estado === 'Borrador', 403);
                    $this->revision($current, (int) $data['revision']);
                }
                if (PersonalFormacion::query()->whereHas('persona')->whereIn('id_personal', $recipients)->lockForUpdate()->get()->count() !== count($recipients)) {
                    throw ValidationException::withMessages(['destinatarios' => 'Alguna participación ya no está disponible. Revisa los destinatarios.']);
                }
                if (! User::query()->whereKey($data['responsable_id'])->whereHas('roles', fn (EloquentBuilder $q): EloquentBuilder => $q->where('codigo', 'administrador'))->exists()) {
                    throw ValidationException::withMessages(['responsable_id' => 'Selecciona un administrador responsable.']);
                }
                if (! DB::table('tipos_correspondencia')->where('id_tipo_correspondencia', $data['id_tipo_correspondencia'])->whereNull('deleted_at')->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['id_tipo_correspondencia' => 'El tipo seleccionado ya no está disponible.']);
                }
                $primary = $current ? DB::table('archivos_correspondencia')->where('id_correspondencia', $id_correspondencia)->where('clase', 'Principal')->first() : null;
                $linkedId = $data['id_adjunto_incidencia'] ?? ($request->hasFile('archivo') ? null : $primary?->id_adjunto_incidencia);
                $linked = null;
                if ($linkedId) {
                    $linked = DB::table('adjuntos_incidencias as a')->join('incidencias as i', 'i.id_incidencia', '=', 'a.id_incidencia')->where('a.id_adjunto', $linkedId)
                        ->where('a.clase', 'oficio')->whereNull('i.deleted_at')->lockForUpdate()->select('a.*', 'i.id_personal')->first();
                    if (! $linked || ! $linked->ruta_archivo || ! Storage::disk('incidencias')->exists($linked->ruta_archivo) || $recipients !== [(int) $linked->id_personal] || ! empty($data['destinatario_externo']) || $data['direccion'] !== 'Emitida') {
                        throw ValidationException::withMessages(['id_adjunto_incidencia' => 'El oficio debe existir y dirigirse únicamente al pasante de esa incidencia, como correspondencia emitida.']);
                    }
                }
                if (! $primary && ! $linked && ! $request->hasFile('archivo')) {
                    throw ValidationException::withMessages(['archivo' => 'Adjunta el documento principal en PDF o selecciona un oficio de incidencias.']);
                }
                $values = collect($data)->only(['id_tipo_correspondencia', 'direccion', 'numero_oficio', 'asunto', 'remitente', 'destinatario_externo', 'descripcion', 'fecha_documento', 'fecha_movimiento', 'requiere_respuesta', 'responsable_id'])->all();
                $values += ['fecha_limite' => $data['requiere_respuesta'] ? ($data['fecha_limite'] ?? null) : null, 'updated_at' => now(), 'revision' => $current ? $current->revision + 1 : 0];
                if ($current) {
                    DB::table('correspondencia')->where('id_correspondencia', $id_correspondencia)->update($values);
                    $id = $id_correspondencia;
                    DB::table('destinatarios_correspondencia')->where('id_correspondencia', $id)->delete();
                } else {
                    $id = DB::table('correspondencia')->insertGetId($values + ['creado_por' => $request->user()->id, 'created_at' => now()]);
                }
                foreach ($recipients as $personal) {
                    DB::table('destinatarios_correspondencia')->insert(['id_correspondencia' => $id, 'id_personal' => $personal, 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($linked || $request->hasFile('archivo')) {
                    if ($primary) {
                        $obsolete = $primary->ruta_archivo;
                        DB::table('archivos_correspondencia')->where('id_archivo', $primary->id_archivo)->delete();
                    }
                    $file = $linked ? ['nombre_archivo' => $linked->nombre_archivo, 'id_adjunto_incidencia' => $linked->id_adjunto] : $this->upload($request->file('archivo'));
                    if (isset($file['ruta_archivo'])) {
                        $newFiles[] = $file['ruta_archivo'];
                    }
                    DB::table('archivos_correspondencia')->insert($file + ['id_correspondencia' => $id, 'clase' => 'Principal', 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($request->file('anexos', []) as $attachment) {
                    $file = $this->upload($attachment);
                    $newFiles[] = $file['ruta_archivo'];
                    DB::table('archivos_correspondencia')->insert($file + ['id_correspondencia' => $id, 'clase' => 'Anexo', 'created_at' => now(), 'updated_at' => now()]);
                }
                $this->history($id, $request->user(), $current ? 'Borrador actualizado' : 'Borrador registrado');

                return $id;
            });
        } catch (Throwable $error) {
            Storage::disk('correspondencia')->delete($newFiles);
            if ($error instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['id_adjunto_incidencia' => 'Este oficio ya tiene un registro de correspondencia. Consulta el registro existente.']);
            }
            throw $error;
        }
        if ($obsolete) {
            Storage::disk('correspondencia')->delete($obsolete);
        }

        return to_route('correspondencia.show', $id)->with('status', 'Borrador guardado. Revisa los destinatarios antes de publicarlo.');
    }

    public function publish(Request $request, int $id_correspondencia): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $id_correspondencia): void {
            $item = $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->lockForUpdate()->first();
            abort_unless($item, 404);
            $this->revision($item, (int) $data['revision']);
            abort_unless($item->estado === 'Borrador', 403);
            $ids = DB::table('destinatarios_correspondencia')->where('id_correspondencia', $id_correspondencia)->pluck('id_personal');
            if (PersonalFormacion::query()->whereHas('persona')->whereIn('id_personal', $ids)->count() !== $ids->count()) {
                throw ValidationException::withMessages(['revision' => 'Algún destinatario ya no está disponible. Edita el borrador antes de publicarlo.']);
            }
            $file = DB::table('archivos_correspondencia')->where('id_correspondencia', $id_correspondencia)->where('clase', 'Principal')->first();
            abort_unless($file, 422);
            [$disk, $path] = $this->fileLocation($file);
            if (! $path || ! Storage::disk($disk)->exists($path)) {
                throw ValidationException::withMessages(['revision' => 'No se encuentra el documento principal. Revisa el borrador.']);
            }
            DB::table('correspondencia')->where('id_correspondencia', $id_correspondencia)->update(['estado' => 'En seguimiento', 'publicado_at' => now(), 'revision' => $item->revision + 1, 'updated_at' => now()]);
            $this->history($id_correspondencia, $request->user(), 'Publicado para seguimiento');
        });

        return back()->with('status', 'Documento disponible para sus destinatarios.');
    }

    public function show(Request $request, int $id_correspondencia): Response
    {
        $user = $request->user();
        $item = $this->visible($user)->where('c.id_correspondencia', $id_correspondencia)->first();
        abort_unless($item, 404);
        $admin = $user->hasRole('administrador');
        $recipients = $this->recipients($user)->where('d.id_correspondencia', $id_correspondencia)->select('d.*', 'p.nombre', 'p.ap', 'p.am')->get();
        if (! $admin) {
            DB::table('destinatarios_correspondencia')->whereIn('id_destinatario', $recipients->pluck('id_destinatario'))->whereNull('consultado_at')->update(['consultado_at' => now(), 'updated_at' => now()]);
        }
        $responses = DB::table('respuestas_correspondencia')->where('id_correspondencia', $id_correspondencia);
        if (! $admin) {
            $responses->whereIn('id_destinatario', $recipients->pluck('id_destinatario'));
        }

        return Inertia::render('correspondencia-detalle', [
            'documento' => $item, 'isAdmin' => $admin, 'destinatarios' => $recipients,
            'archivos' => DB::table('archivos_correspondencia')->where('id_correspondencia', $id_correspondencia)->get(['id_archivo', 'nombre_archivo', 'clase', 'id_adjunto_incidencia']),
            'respuestas' => $responses->orderByDesc('id_respuesta')->get(['id_respuesta', 'id_destinatario', 'comentario', 'nombre_archivo', 'estado', 'observaciones', 'revision', 'created_at', 'revisado_at']),
            'historial' => $admin ? DB::table('historial_correspondencia as h')->join('users as u', 'u.id', '=', 'h.user_id')->where('h.id_correspondencia', $id_correspondencia)->orderByDesc('h.id_historial')->get(['h.id_historial', 'h.evento', 'h.nota', 'h.created_at', 'u.name as autor']) : [],
            'canRespond' => $user->can('correspondencia.registrar'), 'status' => $request->session()->get('status'),
        ]);
    }

    public function acknowledge(Request $request, int $id_correspondencia, int $id_destinatario): RedirectResponse
    {
        abort_if($request->user()->hasRole('administrador'), 403);
        DB::transaction(function () use ($request, $id_correspondencia, $id_destinatario): void {
            $item = $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->lockForUpdate()->first();
            abort_unless($item && $item->estado !== 'Cancelada', 404);
            $recipient = $this->recipients($request->user())->where('d.id_correspondencia', $id_correspondencia)->where('d.id_destinatario', $id_destinatario)->first();
            abort_unless($recipient, 404);
            if (! $recipient->confirmado_at) {
                DB::table('destinatarios_correspondencia')->where('id_destinatario', $id_destinatario)->update(['confirmado_at' => now(), 'confirmado_por' => $request->user()->id, 'updated_at' => now()]);
                $this->history($id_correspondencia, $request->user(), 'Recepción confirmada', 'Participación #'.$recipient->id_personal);
            }
        });

        return back()->with('status', 'Recepción confirmada en la plataforma.');
    }

    public function respond(Request $request, int $id_correspondencia): RedirectResponse
    {
        $data = $request->validate(['id_destinatario' => ['nullable', 'integer'], 'comentario' => ['required', 'string', 'max:5000'], 'archivo' => ['nullable', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240']]);
        $stored = null;
        try {
            DB::transaction(function () use ($request, $data, $id_correspondencia, &$stored): void {
                $item = $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->lockForUpdate()->first();
                abort_unless($item, 404);
                abort_unless($item->estado === 'En seguimiento', 403);
                $admin = $request->user()->hasRole('administrador');
                abort_if($admin && ! $item->destinatario_externo, 403);
                $recipient = null;
                if (! $admin) {
                    abort_unless($item->requiere_respuesta, 403);
                    $recipient = $this->recipients($request->user())->where('d.id_correspondencia', $id_correspondencia)->where('d.id_destinatario', $data['id_destinatario'] ?? 0)->first();
                    abort_unless($recipient, 404);
                    if (! $recipient->confirmado_at) {
                        throw ValidationException::withMessages(['comentario' => 'Confirma la recepción antes de responder.']);
                    }
                    $last = DB::table('respuestas_correspondencia')->where('id_destinatario', $recipient->id_destinatario)->latest('id_respuesta')->first();
                    if ($last && $last->estado !== 'Por corregir') {
                        throw ValidationException::withMessages(['comentario' => 'Ya entregaste una respuesta. Espera su revisión antes de enviar otra.']);
                    }
                }
                $file = $request->hasFile('archivo') ? $this->upload($request->file('archivo')) : [];
                $stored = $file['ruta_archivo'] ?? null;
                DB::table('respuestas_correspondencia')->insert($file + ['id_correspondencia' => $id_correspondencia, 'id_destinatario' => $recipient?->id_destinatario,
                    'user_id' => $request->user()->id, 'comentario' => $data['comentario'], 'estado' => $admin ? 'Atendida' : 'Pendiente',
                    'revisado_por' => $admin ? $request->user()->id : null, 'revisado_at' => $admin ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
                $this->history($id_correspondencia, $request->user(), $admin ? 'Respuesta o acuse externo registrado' : 'Respuesta del destinatario recibida');
            });
        } catch (Throwable $error) {
            if ($stored) {
                Storage::disk('correspondencia')->delete($stored);
            }
            throw $error;
        }

        return back()->with('status', 'Respuesta registrada.');
    }

    public function review(Request $request, int $id_correspondencia, int $id_respuesta): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', Rule::in(['Atendida', 'Por corregir'])], 'observaciones' => ['nullable', 'required_if:estado,Por corregir', 'string', 'max:3000'], 'revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $id_correspondencia, $id_respuesta): void {
            $item = $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_unless($item->estado === 'En seguimiento', 403);
            $response = DB::table('respuestas_correspondencia')->where('id_correspondencia', $id_correspondencia)->where('id_respuesta', $id_respuesta)->lockForUpdate()->first();
            abort_unless($response && $response->id_destinatario, 404);
            $this->revision($response, (int) $data['revision']);
            if ($response->estado !== 'Pendiente') {
                throw ValidationException::withMessages(['revision' => 'Esta respuesta ya fue revisada. Se conserva su resultado.']);
            }
            DB::table('respuestas_correspondencia')->where('id_respuesta', $id_respuesta)->update(['estado' => $data['estado'], 'observaciones' => $data['observaciones'] ?? null,
                'revisado_por' => $request->user()->id, 'revisado_at' => now(), 'revision' => $response->revision + 1, 'updated_at' => now()]);
            $this->history($id_correspondencia, $request->user(), 'Respuesta #'.$id_respuesta.' revisada', $data['estado'].': '.($data['observaciones'] ?? ''));
        });

        return back()->with('status', 'Revisión guardada.');
    }

    public function transition(Request $request, int $id_correspondencia): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', Rule::in(['En seguimiento', 'Atendida', 'Cancelada'])], 'nota' => ['required', 'string', 'max:3000'], 'revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $id_correspondencia): void {
            $item = $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->lockForUpdate()->first();
            abort_unless($item, 404);
            $this->revision($item, (int) $data['revision']);
            $allowed = ['Borrador' => ['Cancelada'], 'En seguimiento' => ['Atendida', 'Cancelada'], 'Atendida' => ['En seguimiento'], 'Cancelada' => []];
            if (! in_array($data['estado'], $allowed[$item->estado], true)) {
                throw ValidationException::withMessages(['estado' => 'La transición no está disponible para el estado actual.']);
            }
            if ($data['estado'] === 'Atendida' && $item->requiere_respuesta) {
                $pending = DB::table('destinatarios_correspondencia as d')->where('d.id_correspondencia', $id_correspondencia)
                    ->whereNotExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('respuestas_correspondencia as r')->whereColumn('r.id_destinatario', 'd.id_destinatario')->where('r.estado', 'Atendida'))->exists();
                $external = $item->destinatario_externo && ! DB::table('respuestas_correspondencia')->where('id_correspondencia', $id_correspondencia)->whereNull('id_destinatario')->exists();
                if ($pending || $external) {
                    throw ValidationException::withMessages(['estado' => 'Falta atender respuestas de destinatarios o registrar la respuesta de la contraparte externa.']);
                }
            }
            DB::table('correspondencia')->where('id_correspondencia', $id_correspondencia)->update(['estado' => $data['estado'], 'revision' => $item->revision + 1, 'updated_at' => now()]);
            $this->history($id_correspondencia, $request->user(), $item->estado.' → '.$data['estado'], $data['nota']);
        });

        return back()->with('status', 'Seguimiento actualizado.');
    }

    public function destroy(Request $request, int $id_correspondencia): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $id_correspondencia): void {
            $item = $this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->lockForUpdate()->first();
            abort_unless($item, 404);
            $this->revision($item, (int) $data['revision']);
            abort_unless($item->estado === 'Borrador', 403);
            DB::table('correspondencia')->where('id_correspondencia', $id_correspondencia)->update(['deleted_at' => now(), 'updated_at' => now(), 'revision' => $item->revision + 1]);
            $this->history($id_correspondencia, $request->user(), 'Borrador eliminado');
        });

        return to_route('correspondencia.index')->with('status', 'Borrador eliminado.');
    }

    /** @return array{string, ?string} */
    private function fileLocation(object $file): array
    {
        if ($file->id_adjunto_incidencia) {
            return ['incidencias', DB::table('adjuntos_incidencias as a')->join('incidencias as i', 'i.id_incidencia', '=', 'a.id_incidencia')->whereNull('i.deleted_at')
                ->where('a.id_adjunto', $file->id_adjunto_incidencia)->where('a.clase', 'oficio')->value('a.ruta_archivo')];
        }

        return ['correspondencia', $file->ruta_archivo];
    }

    public function download(Request $request, int $id_correspondencia, int $id_archivo): StreamedResponse
    {
        abort_unless($this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->exists(), 404);
        $file = DB::table('archivos_correspondencia')->where('id_correspondencia', $id_correspondencia)->where('id_archivo', $id_archivo)->first();
        abort_unless($file, 404);
        [$disk, $path] = $this->fileLocation($file);
        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $file->nombre_archivo, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function downloadResponse(Request $request, int $id_correspondencia, int $id_respuesta): StreamedResponse
    {
        abort_unless($this->visible($request->user())->where('c.id_correspondencia', $id_correspondencia)->exists(), 404);
        $query = DB::table('respuestas_correspondencia')->where('id_correspondencia', $id_correspondencia)->where('id_respuesta', $id_respuesta);
        if (! $request->user()->hasRole('administrador')) {
            $query->whereIn('id_destinatario', $this->recipients($request->user())->select('d.id_destinatario'));
        }
        $file = $query->first();
        abort_unless($file && $file->ruta_archivo && Storage::disk('correspondencia')->exists($file->ruta_archivo), 404);

        return Storage::disk('correspondencia')->download($file->ruta_archivo, $file->nombre_archivo, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
