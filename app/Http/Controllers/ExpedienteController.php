<?php

namespace App\Http\Controllers;

use App\Models\PersonalFormacion;
use App\Models\User;
use App\Promociones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExpedienteController extends Controller
{
    /** @return Builder<PersonalFormacion> */
    private function visible(User $user): Builder
    {
        $query = PersonalFormacion::query()->whereHas('persona');
        if (! $user->hasRole('administrador')) {
            abort_unless($user->hasRole('pasante'), 403);
            $query->where('id_persona', $user->id_persona ?? 0);
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);
        $query = $this->visible($request->user())->with('persona:id_persona,nombre,ap,am');
        Promociones::filter($request, $query);
        if (! empty($filters['buscar'])) {
            $query->whereHas('persona', fn (Builder $person): Builder => $person->where(function (Builder $names) use ($filters): void {
                foreach (['nombre', 'ap', 'am'] as $column) {
                    $names->orWhere($column, 'like', '%'.$filters['buscar'].'%');
                }
            }));
        }
        $query->addSelect(['entregados' => DB::table('documentos_expediente as d')->selectRaw('COUNT(*)')
            ->whereColumn('d.id_personal', 'personal_formacion.id_personal')
            ->whereExists(fn (QueryBuilder $files): QueryBuilder => $files->selectRaw('1')->from('archivos_expediente as a')
                ->whereColumn('a.id_documento_expediente', 'd.id_documento_expediente')->whereNull('a.deleted_at'))]);

        return Inertia::render('expedientes', [
            'participaciones' => $query->orderByDesc('id_personal')->paginate(15)->withQueryString(),
            'buscar' => $filters['buscar'] ?? '', 'isAdmin' => $request->user()->hasRole('administrador'),
        ]);
    }

    public function show(Request $request, int $id_personal): Response
    {
        $personal = $this->visible($request->user())->with('persona:id_persona,nombre,ap,am')->findOrFail($id_personal);
        $rows = DB::table('tipos_expediente as t')->leftJoin('documentos_expediente as d', function (JoinClause $join) use ($id_personal): void {
            $join->on('d.id_tipo_expediente', '=', 't.id_tipo_expediente')->where('d.id_personal', $id_personal);
        })->select('t.id_tipo_expediente', 't.nombre', 'd.id_documento_expediente', 'd.observaciones')->orderBy('t.id_tipo_expediente')->get();
        $files = DB::table('archivos_expediente')->whereIn('id_documento_expediente', $rows->pluck('id_documento_expediente')->filter())
            ->whereNull('deleted_at')->select('id_archivo_expediente', 'id_documento_expediente', 'nombre_archivo', 'tamano', 'created_at')
            ->orderBy('id_archivo_expediente')->get()->groupBy('id_documento_expediente');
        $rows->each(function (object $row) use ($files): void {
            $row->archivos = $files->get($row->id_documento_expediente, collect())->values();
        });

        return Inertia::render('expediente', [
            'personal' => $personal, 'documentos' => $rows,
            'canUpload' => ! $request->user()->hasRole('administrador') && $request->user()->can('expedientes.registrar'),
            'canDelete' => ! $request->user()->hasRole('administrador') && $request->user()->can('expedientes.eliminar'),
            'canReview' => $request->user()->hasRole('administrador') && $request->user()->can('expedientes.revisar'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, int $id_personal, int $id_tipo_expediente): RedirectResponse
    {
        abort_if($request->user()->hasRole('administrador'), 403);
        $this->visible($request->user())->findOrFail($id_personal);
        abort_unless(DB::table('tipos_expediente')->where('id_tipo_expediente', $id_tipo_expediente)->exists(), 404);
        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240']], [
            'archivo.required' => 'Selecciona el archivo que vas a entregar.',
            'archivo.mimes' => 'Solo se permiten documentos PDF o imágenes JPG y PNG.',
            'archivo.extensions' => 'El archivo debe tener extensión PDF, JPG o PNG.',
            'archivo.max' => 'Cada archivo debe pesar como máximo 10 MB.',
            'archivo.uploaded' => 'No se pudo recibir el archivo. Revisa su tamaño e intenta de nuevo.',
        ]);
        $file = $request->file('archivo');
        $path = $file->store((string) $id_personal, 'expedientes');
        try {
            DB::transaction(function () use ($request, $id_personal, $id_tipo_expediente, $file, $path): void {
                $this->visible($request->user())->lockForUpdate()->findOrFail($id_personal);
                $document = $this->document($id_personal, $id_tipo_expediente);
                DB::table('archivos_expediente')->insert([
                    'id_documento_expediente' => $document, 'user_id' => $request->user()->id,
                    'nombre_archivo' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                    'ruta_archivo' => $path, 'mime' => $file->getMimeType(), 'tamano' => $file->getSize(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('expedientes')->delete($path);
            throw $exception;
        }

        return to_route('expedientes.show', $id_personal)->with('status', 'Archivo entregado. El administrador ya puede consultarlo.');
    }

    private function document(int $personal, int $type): int
    {
        $existing = DB::table('documentos_expediente')->where('id_personal', $personal)->where('id_tipo_expediente', $type)->value('id_documento_expediente');

        return $existing ?? DB::table('documentos_expediente')->insertGetId([
            'id_personal' => $personal, 'id_tipo_expediente' => $type, 'created_at' => now(), 'updated_at' => now(),
        ], 'id_documento_expediente');
    }

    public function observations(Request $request, int $id_personal, int $id_tipo_expediente): RedirectResponse
    {
        abort_unless($request->user()->hasRole('administrador'), 403);
        $data = $request->validate(['observaciones' => ['nullable', 'string', 'max:2000']]);
        abort_unless(DB::table('tipos_expediente')->where('id_tipo_expediente', $id_tipo_expediente)->exists(), 404);
        DB::transaction(function () use ($request, $id_personal, $id_tipo_expediente, $data): void {
            $this->visible($request->user())->lockForUpdate()->findOrFail($id_personal);
            $id = $this->document($id_personal, $id_tipo_expediente);
            DB::table('documentos_expediente')->where('id_documento_expediente', $id)->update(['observaciones' => $data['observaciones'] ?? null, 'updated_at' => now()]);
        });

        return to_route('expedientes.show', $id_personal)->with('status', 'Observaciones guardadas.');
    }

    private function file(Request $request, int $personal, int $file): object
    {
        $this->visible($request->user())->findOrFail($personal);
        $record = DB::table('archivos_expediente as a')->join('documentos_expediente as d', 'd.id_documento_expediente', '=', 'a.id_documento_expediente')
            ->where('d.id_personal', $personal)->where('a.id_archivo_expediente', $file)->whereNull('a.deleted_at')->select('a.*')->first();
        abort_unless($record, 404);

        return $record;
    }

    public function download(Request $request, int $id_personal, int $id_archivo_expediente): StreamedResponse
    {
        $file = $this->file($request, $id_personal, $id_archivo_expediente);
        abort_unless(Storage::disk('expedientes')->exists($file->ruta_archivo), 404);

        return Storage::disk('expedientes')->download($file->ruta_archivo, $file->nombre_archivo, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, int $id_personal, int $id_archivo_expediente): RedirectResponse
    {
        abort_if($request->user()->hasRole('administrador'), 403);
        $file = $this->file($request, $id_personal, $id_archivo_expediente);
        DB::transaction(function () use ($file): void {
            DB::table('archivos_expediente')->where('id_archivo_expediente', $file->id_archivo_expediente)->lockForUpdate()->first();
            if (DB::table('adjuntos_incidencias')->where('id_archivo_expediente', $file->id_archivo_expediente)->exists()) {
                throw ValidationException::withMessages(['archivo' => 'Este archivo respalda una incidencia y debe conservarse. Puedes agregar una copia corregida.']);
            }
            DB::table('archivos_expediente')->where('id_archivo_expediente', $file->id_archivo_expediente)->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'updated_at' => now()]);
        });

        return to_route('expedientes.show', $id_personal)->with('status', 'Archivo retirado del expediente.');
    }
}
