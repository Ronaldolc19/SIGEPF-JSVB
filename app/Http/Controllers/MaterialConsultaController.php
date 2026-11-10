<?php

namespace App\Http\Controllers;

use App\Models\User;
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

class MaterialConsultaController extends Controller
{
    private const STATES = ['Borrador', 'Publicado', 'Retirado'];

    private function visible(User $user): Builder
    {
        $query = DB::table('materiales_consultas as m')->whereNull('m.deleted_at');
        if ($user->hasRole('administrador')) {
            return $query;
        }
        abort_unless($user->hasRole('pasante'), 403);

        return $query->where('m.estado', 'Publicado')->whereNotNull('m.fecha_publicacion')->where('m.fecha_publicacion', '<=', now())
            ->whereExists(function (Builder $profile) use ($user): void {
                $profile->selectRaw('1')->from('personal_formacion as p')->join('personas as person', 'person.id_persona', '=', 'p.id_persona')
                    ->where('p.id_persona', $user->id_persona ?? 0)->whereNull('p.deleted_at')->whereNull('person.deleted_at')
                    ->where(fn (Builder $type): Builder => $type->whereNull('m.id_tipo_personal')->orWhereColumn('m.id_tipo_personal', 'p.id_tipo_personal'))
                    ->where(fn (Builder $career): Builder => $career->whereNull('m.id_carrera')->orWhereExists(fn (Builder $academic): Builder => $academic
                        ->selectRaw('1')->from('datos_academicos as a')->whereColumn('a.id_personal', 'p.id_personal')->whereNull('a.deleted_at')->whereColumn('a.id_carrera', 'm.id_carrera')));
            });
    }

    private function catalogs(): array
    {
        return [
            'tipos' => DB::table('tipos_documentos')->whereNull('deleted_at')->orderBy('nombre')->get(['id_tipo_documento as id', 'nombre']),
            'carreras' => DB::table('carreras')->whereNull('deleted_at')->orderBy('nombre')->get(['id_carrera as id', 'nombre']),
            'personalTipos' => DB::table('tipos_personal')->whereNull('deleted_at')->orderBy('nombre')->get(['id_tipo_personal as id', 'nombre']),
        ];
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'id_tipo_documento' => ['nullable', 'integer'], 'id_carrera' => ['nullable', 'integer'], 'id_tipo_personal' => ['nullable', 'integer'], 'estado' => ['nullable', Rule::in(self::STATES)]]);
        $admin = $request->user()->hasRole('administrador');
        $query = $this->visible($request->user());
        if (! empty($filters['buscar'])) {
            $query->where(fn (Builder $search): Builder => $search->where('m.titulo', 'like', '%'.$filters['buscar'].'%')->orWhere('m.descripcion', 'like', '%'.$filters['buscar'].'%'));
        }
        foreach (['id_tipo_documento', 'id_carrera', 'id_tipo_personal'] as $key) {
            if (! empty($filters[$key])) {
                $query->where('m.'.$key, $filters[$key]);
            }
        }
        if ($admin && ! empty($filters['estado'])) {
            $query->where('m.estado', $filters['estado']);
        }

        return Inertia::render('consulta', [
            'materiales' => $query->leftJoin('tipos_documentos as t', 't.id_tipo_documento', '=', 'm.id_tipo_documento')
                ->leftJoin('carreras as c', 'c.id_carrera', '=', 'm.id_carrera')->leftJoin('tipos_personal as p', 'p.id_tipo_personal', '=', 'm.id_tipo_personal')
                ->select('m.id_material', 'm.titulo', 'm.descripcion', 'm.nombre_archivo', 'm.estado', 'm.fecha_publicacion', 't.nombre as tipo', 'c.nombre as carrera', 'p.nombre as tipo_personal')
                ->orderByDesc('m.updated_at')->orderByDesc('m.id_material')->paginate(12)->withQueryString(),
            ...$this->catalogs(), 'filters' => $filters, 'isAdmin' => $admin, 'estados' => self::STATES,
            'linked' => $admin || DB::table('personal_formacion as p')->join('personas as person', 'person.id_persona', '=', 'p.id_persona')
                ->where('p.id_persona', $request->user()->id_persona ?? 0)->whereNull('p.deleted_at')->whereNull('person.deleted_at')->exists(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('consulta-form', ['material' => null, ...$this->catalogs(), 'estados' => self::STATES]);
    }

    public function edit(Request $request, int $id_material): Response
    {
        $material = $this->visible($request->user())->where('id_material', $id_material)
            ->first(['id_material', 'titulo', 'descripcion', 'id_tipo_documento', 'id_tipo_personal', 'id_carrera', 'nombre_archivo', 'estado', 'revision']);
        abort_unless($material, 404);

        return Inertia::render('consulta-form', ['material' => $material, ...$this->catalogs(), 'estados' => self::STATES]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id_material): RedirectResponse
    {
        return $this->save($request, $id_material);
    }

    private function save(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'], 'descripcion' => ['nullable', 'string', 'max:5000'],
            'id_tipo_documento' => ['required', 'integer', Rule::exists('tipos_documentos', 'id_tipo_documento')->whereNull('deleted_at')],
            'id_tipo_personal' => ['nullable', 'integer', Rule::exists('tipos_personal', 'id_tipo_personal')->whereNull('deleted_at')],
            'id_carrera' => ['nullable', 'integer', Rule::exists('carreras', 'id_carrera')->whereNull('deleted_at')],
            'estado' => ['required', Rule::in(self::STATES)], 'revision' => [$id ? 'required' : 'nullable', 'integer', 'min:0'],
            'archivo' => [$id ? 'nullable' : 'required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png', 'extensions:pdf,docx,xlsx,jpg,jpeg,png', 'max:20480'],
        ], ['required' => 'Completa este dato.', 'exists' => 'Selecciona una opción vigente.', 'archivo.max' => 'El archivo debe pesar como máximo 20 MB.',
            'archivo.mimes' => 'Selecciona un archivo PDF, DOCX, XLSX, JPG o PNG.', 'archivo.extensions' => 'La extensión debe ser PDF, DOCX, XLSX, JPG o PNG.', 'archivo.uploaded' => 'No se pudo recibir el archivo. Revisa su tamaño e intenta de nuevo.']);
        if ($data['estado'] === 'Publicado') {
            abort_unless($request->user()->can('consulta.publicar'), 403);
        }
        $file = $request->file('archivo');
        $path = $file?->store('materiales', 'consulta');
        $oldPath = null;
        try {
            DB::transaction(function () use ($request, $data, $id, $file, $path, &$oldPath): void {
                $current = $id ? $this->visible($request->user())->where('id_material', $id)->lockForUpdate()->first() : null;
                if ($id) {
                    abort_unless($current, 404);
                    if ((int) $current->revision !== (int) $data['revision']) {
                        throw ValidationException::withMessages(['revision' => 'Otro cambio fue guardado. Recarga el formulario antes de continuar.']);
                    }
                }
                foreach (['id_tipo_documento' => 'tipos_documentos', 'id_tipo_personal' => 'tipos_personal', 'id_carrera' => 'carreras'] as $key => $table) {
                    if (! empty($data[$key]) && ! DB::table($table)->where($key, $data[$key])->whereNull('deleted_at')->lockForUpdate()->first()) {
                        throw ValidationException::withMessages([$key => 'La opción ya no está disponible.']);
                    }
                }
                $values = [
                    'titulo' => $data['titulo'], 'descripcion' => $data['descripcion'] ?? null, 'id_tipo_documento' => $data['id_tipo_documento'],
                    'id_tipo_personal' => $data['id_tipo_personal'] ?? null, 'id_carrera' => $data['id_carrera'] ?? null,
                    'estado' => $data['estado'], 'fecha_publicacion' => $data['estado'] === 'Publicado' && $current?->estado !== 'Publicado' ? now() : $current?->fecha_publicacion,
                    'revision' => $current ? $current->revision + 1 : 0, 'updated_at' => now(),
                ];
                if ($file) {
                    $values['ruta_archivo'] = $path;
                    $values['nombre_archivo'] = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
                    $oldPath = $current?->ruta_archivo;
                }
                if ($data['estado'] === 'Publicado' && ! $file && (! $current || ! Storage::disk('consulta')->exists($current->ruta_archivo))) {
                    throw ValidationException::withMessages(['archivo' => 'Adjunta un archivo disponible antes de publicar.']);
                }
                if ($current) {
                    DB::table('materiales_consultas')->where('id_material', $id)->update($values);
                } else {
                    DB::table('materiales_consultas')->insert([...$values, 'created_at' => now()]);
                }
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('consulta')->delete($path);
            }
            throw $error;
        }
        if ($oldPath && $oldPath !== $path) {
            try {
                Storage::disk('consulta')->delete($oldPath);
            } catch (Throwable $error) {
                report($error);
            }
        }

        return to_route('consulta.index')->with('status', $data['estado'] === 'Publicado' ? 'Material publicado para sus destinatarios.' : 'Material guardado como '.$data['estado'].'.');
    }

    public function download(Request $request, int $id_material): StreamedResponse
    {
        $material = $this->visible($request->user())->where('id_material', $id_material)->first();
        abort_unless($material && Storage::disk('consulta')->exists($material->ruta_archivo), 404);

        return Storage::disk('consulta')->download($material->ruta_archivo, $material->nombre_archivo, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, int $id_material): RedirectResponse
    {
        $updated = $this->visible($request->user())->where('id_material', $id_material)->update(['deleted_at' => now(), 'updated_at' => now()]);
        abort_unless($updated, 404);

        return to_route('consulta.index')->with('status', 'Material eliminado del portal.');
    }
}
