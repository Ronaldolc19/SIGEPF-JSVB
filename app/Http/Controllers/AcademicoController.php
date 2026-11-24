<?php

namespace App\Http\Controllers;

use App\Models\AsignacionAsesor;
use App\Models\PersonalFormacion;
use App\Models\User;
use App\Promociones;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AcademicoController extends Controller
{
    private function people(User $user, bool $review = false): EloquentBuilder
    {
        $query = PersonalFormacion::query()->whereHas('persona');
        if ($user->hasRole('administrador')) {
            return $query;
        }
        $modules = $user->modulesByRole();

        return $query->where(function (EloquentBuilder $scope) use ($user, $modules, $review): void {
            $scope->whereRaw('1 = 0');
            if (! $review && in_array('academico', $modules['pasante'] ?? [], true)) {
                $scope->orWhere('id_persona', $user->id_persona ?? 0);
            }
            if (in_array('academico', $modules['asesor'] ?? [], true)) {
                $scope->orWhereHas('asignaciones', fn (EloquentBuilder $q): EloquentBuilder => $q->vigentes()
                    ->whereHas('asesor', fn (EloquentBuilder $a): EloquentBuilder => $a->where('id_persona', $user->id_persona ?? 0)));
            }
        });
    }

    private function documents(User $user): Builder
    {
        return DB::table('documentos_investigacion as d')->whereNull('d.deleted_at')
            ->whereIn('d.id_personal', $this->people($user)->select('id_personal'));
    }

    private function latest(Builder $query): Builder
    {
        return $query->whereNotExists(fn (Builder $next): Builder => $next->selectRaw('1')->from('documentos_investigacion as n')->whereColumn('n.id_documento_anterior', 'd.id_documento'));
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', Rule::in(['Pendiente de revisión', 'Por corregir', 'Aprobado'])]]);
        $query = $this->latest($this->documents($request->user()))->join('personal_formacion as pf', 'pf.id_personal', '=', 'd.id_personal')
            ->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')->join('tipos_documentos as t', 't.id_tipo_documento', '=', 'd.id_tipo_documento');
        Promociones::filter($request, $query, 'pf.id_promocion');
        foreach (preg_split('/\s+/u', trim($filters['buscar'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(function (Builder $names) use ($term): void {
                foreach (['p.nombre', 'p.ap', 'p.am', 'd.titulo', 't.nombre'] as $column) {
                    $names->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }
        if (! empty($filters['estado'])) {
            $query->where('d.estado', $filters['estado']);
        }

        return Inertia::render('academico', [
            'documentos' => $query->select('d.id_documento', 'd.titulo', 'd.nombre_archivo', 'd.version', 'd.estado', 'd.created_at', 'p.nombre', 'p.ap', 'p.am', 't.nombre as tipo')
                ->orderBy('p.ap')->orderBy('p.am')->orderBy('p.nombre')->orderByDesc('d.id_documento')->paginate(15)->withQueryString(),
            'filters' => $filters, 'canUpload' => $request->user()->hasRole('pasante') && $request->user()->can('academico.registrar'),
            'isAdmin' => $request->user()->hasRole('administrador'), 'status' => $request->session()->get('status'),
        ]);
    }

    public function form(Request $request): Response
    {
        abort_unless($request->user()->hasRole('pasante'), 403);

        $people = PersonalFormacion::query()->whereHas('persona')->where('id_persona', $request->user()->id_persona ?? 0)->where('estado', 'En formacion');
        Promociones::filter($request, $people);

        return Inertia::render('academico-form', [
            'personal' => $people->with('persona:id_persona,nombre,ap,am')->orderByDesc('fecha_ingreso')->orderByDesc('id_personal')->limit(1)
                ->get(['id_personal', 'id_persona', 'fecha_ingreso', 'fecha_termino']),
            'tipos' => DB::table('tipos_documentos')->whereNull('deleted_at')->orderBy('nombre')->get(['id_tipo_documento', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('pasante'), 403);
        $data = $request->validate([
            'id_personal' => ['required', 'integer'], 'id_tipo_documento' => ['required', 'integer'],
            'id_documento_anterior' => ['nullable', 'integer'], 'revision' => ['required_with:id_documento_anterior', 'nullable', 'integer', 'min:0'],
            'titulo' => ['required', 'string', 'max:255'], 'archivo' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240'],
        ], ['archivo.mimes' => 'Adjunta un PDF válido.', 'archivo.extensions' => 'El archivo debe tener extensión PDF.', 'archivo.max' => 'El archivo admite hasta 10 MB.']);
        $path = null;
        try {
            $id = DB::transaction(function () use ($request, $data, &$path): int {
                $person = PersonalFormacion::query()->whereHas('persona')->where('id_persona', $request->user()->id_persona ?? 0)->whereKey($data['id_personal'])->lockForUpdate()->firstOrFail();
                abort_unless($person->estado === 'En formacion', 403);
                $previous = null;
                if (! empty($data['id_documento_anterior'])) {
                    $previous = DB::table('documentos_investigacion')->where('id_documento', $data['id_documento_anterior'])->where('id_personal', $person->id_personal)->whereNull('deleted_at')->lockForUpdate()->first();
                    abort_unless($previous, 404);
                    if ($previous->estado !== 'Por corregir' || $previous->revision !== (int) $data['revision'] || DB::table('documentos_investigacion')->where('id_documento_anterior', $previous->id_documento)->exists()) {
                        throw ValidationException::withMessages(['archivo' => 'Esta versión ya cambió o no admite otra entrega. Recarga el documento.']);
                    }
                    if ($previous->id_tipo_documento !== (int) $data['id_tipo_documento']) {
                        throw ValidationException::withMessages(['archivo' => 'La nueva versión conserva el tipo del documento original.']);
                    }
                } else {
                    if (! DB::table('tipos_documentos')->where('id_tipo_documento', $data['id_tipo_documento'])->whereNull('deleted_at')->exists()) {
                        throw ValidationException::withMessages(['id_tipo_documento' => 'Selecciona un tipo vigente.']);
                    }
                    if (DB::table('documentos_investigacion')->where('id_personal', $person->id_personal)->where('id_tipo_documento', $data['id_tipo_documento'])->whereNull('deleted_at')->exists()) {
                        throw ValidationException::withMessages(['id_tipo_documento' => 'Ya entregaste este tipo de documento. Abre su detalle para consultar la revisión o entregar una corrección.']);
                    }
                }
                $file = $request->file('archivo');
                $path = $file->store((string) $person->id_personal, 'academico');

                return DB::table('documentos_investigacion')->insertGetId([
                    'id_personal' => $person->id_personal, 'id_tipo_documento' => $data['id_tipo_documento'], 'id_documento_anterior' => $previous?->id_documento,
                    'titulo' => $previous ? ($previous->titulo ?? $previous->nombre_archivo) : $data['titulo'],
                    'nombre_archivo' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'ruta_archivo' => $path,
                    'version' => ($previous?->version ?? 0) + 1, 'estado' => 'Pendiente de revisión', 'subido_por' => $request->user()->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('academico')->delete($path);
            }
            throw $error;
        }

        return to_route('academico.show', $id)->with('status', 'Documento entregado para revisión.');
    }

    public function show(Request $request, int $id_documento): Response
    {
        $document = $this->documents($request->user())->where('d.id_documento', $id_documento)->first();
        abort_unless($document, 404);
        $person = $this->people($request->user())->with('persona:id_persona,nombre,ap,am')->findOrFail($document->id_personal);
        $all = DB::table('documentos_investigacion')->where('id_personal', $document->id_personal)->where('id_tipo_documento', $document->id_tipo_documento)->whereNull('deleted_at')
            ->get(['id_documento', 'id_documento_anterior', 'titulo', 'id_personal', 'id_tipo_documento', 'nombre_archivo', 'version', 'estado', 'revision', 'created_at'])->keyBy('id_documento');
        $current = $all->get($id_documento);
        $seen = [];
        while ($next = $all->first(fn (object $v): bool => $v->id_documento_anterior === $current->id_documento && ! in_array($v->id_documento, $seen, true))) {
            $seen[] = $current->id_documento;
            $current = $next;
        }
        $latest = $current;
        $versions = [];
        while ($current && ! isset($versions[$current->id_documento])) {
            $versions[$current->id_documento] = $current;
            $current = $all->get($current->id_documento_anterior);
        }
        $canReview = $request->user()->can('academico.revisar') && $this->people($request->user(), true)->whereKey($person->id_personal)->exists()
            && $person->id_persona !== $request->user()->id_persona && $latest->estado === 'Pendiente de revisión';

        return Inertia::render('academico-detalle', [
            'documento' => $latest, 'versiones' => array_values($versions),
            'persona' => trim($person->persona->ap.' '.$person->persona->am.' '.$person->persona->nombre),
            'tipo' => DB::table('tipos_documentos')->where('id_tipo_documento', $document->id_tipo_documento)->value('nombre'),
            'observaciones' => DB::table('observaciones_documentos as o')->join('users as u', 'u.id', '=', 'o.user_id')->whereIn('o.id_documento', array_keys($versions))->whereNull('o.deleted_at')
                ->orderByDesc('o.id_revision_doc')->get(['o.id_revision_doc', 'o.id_documento', 'o.observacion', 'o.estado', 'o.fecha_observacion', 'u.name as revisor']),
            'canReview' => $canReview,
            'canUpload' => $request->user()->hasRole('pasante') && $request->user()->can('academico.registrar') && $person->id_persona === $request->user()->id_persona && $person->estado === 'En formacion' && $latest->estado === 'Por corregir',
            'status' => $request->session()->get('status'),
        ]);
    }

    public function review(Request $request, int $id_documento): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', Rule::in(['Por corregir', 'Aprobado'])], 'observacion' => ['required', 'string', 'max:5000'], 'revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $id_documento, $data): void {
            $document = $this->documents($request->user())->where('d.id_documento', $id_documento)->first();
            abort_unless($document, 404);
            $person = $this->people($request->user(), true)->whereKey($document->id_personal)->lockForUpdate()->firstOrFail();
            abort_if($person->id_persona === $request->user()->id_persona, 403);
            $document = DB::table('documentos_investigacion')->where('id_documento', $id_documento)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($document, 404);
            if ($document->revision !== (int) $data['revision'] || $document->estado !== 'Pendiente de revisión' || DB::table('documentos_investigacion')->where('id_documento_anterior', $id_documento)->exists()) {
                throw ValidationException::withMessages(['revision' => 'El documento ya fue revisado o tiene una nueva versión. Recarga la página.']);
            }
            $advisor = AsignacionAsesor::query()->vigentes()->where('id_personal', $person->id_personal)->whereHas('asesor', fn (EloquentBuilder $q): EloquentBuilder => $q->where('id_persona', $request->user()->id_persona ?? 0))->value('id_asesor');
            DB::table('observaciones_documentos')->insert(['id_documento' => $id_documento, 'user_id' => $request->user()->id, 'id_asesor' => $advisor,
                'observacion' => $data['observacion'], 'estado' => $data['estado'], 'fecha_observacion' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('documentos_investigacion')->where('id_documento', $id_documento)->update(['estado' => $data['estado'], 'revision' => $document->revision + 1, 'updated_at' => now()]);
        });

        return back()->with('status', 'Revisión registrada. Se conserva en el historial de esta versión.');
    }

    public function download(Request $request, int $id_documento): StreamedResponse
    {
        $document = $this->documents($request->user())->where('d.id_documento', $id_documento)->first();
        abort_unless($document && Storage::disk('academico')->exists($document->ruta_archivo), 404);

        return Storage::disk('academico')->download($document->ruta_archivo, $document->nombre_archivo, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
