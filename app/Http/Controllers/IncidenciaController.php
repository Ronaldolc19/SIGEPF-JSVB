<?php

namespace App\Http\Controllers;

use App\Models\PersonalFormacion;
use App\Models\User;
use App\Promociones;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IncidenciaController extends Controller
{
    public const STATES = ['Registrada', 'En revisión', 'Pendiente de documentación', 'Cerrada'];

    private function people(User $user, bool $staff = false): EloquentBuilder
    {
        $query = PersonalFormacion::query()->whereHas('persona');
        if ($user->hasRole('administrador')) {
            return $query;
        }
        $modules = $user->modulesByRole();
        $advisor = in_array('incidencias', $modules['asesor'] ?? [], true);
        $trainee = ! $staff && in_array('incidencias', $modules['pasante'] ?? [], true);

        return $query->where(function (EloquentBuilder $scope) use ($user, $advisor, $trainee): void {
            $scope->whereRaw('1 = 0');
            if ($advisor) {
                $scope->orWhereHas('asignaciones', fn (EloquentBuilder $assignments): EloquentBuilder => $assignments->vigentes()
                    ->whereHas('asesor', fn (EloquentBuilder $advisors): EloquentBuilder => $advisors->where('id_persona', $user->id_persona ?? 0)));
            }
            if ($trainee) {
                $scope->orWhere('id_persona', $user->id_persona ?? 0);
            }
        });
    }

    private function incidents(User $user, bool $staff = false): Builder
    {
        return DB::table('incidencias')->whereNull('incidencias.deleted_at')
            ->whereIn('incidencias.id_personal', $this->people($user, $staff)->select('id_personal'));
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['estado' => ['nullable', Rule::in(self::STATES)], 'buscar' => ['nullable', 'string', 'max:100']]);
        $query = $this->incidents($request->user())->join('personal_formacion as pf', 'pf.id_personal', '=', 'incidencias.id_personal')
            ->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')->join('tipos_incidencias as t', 't.id_tipo_incidencia', '=', 'incidencias.id_tipo_incidencia');
        Promociones::filter($request, $query, 'pf.id_promocion');
        if (! empty($filters['estado'])) {
            $query->where('incidencias.estado', $filters['estado']);
        }
        if (! empty($filters['buscar'])) {
            $query->where(function (Builder $names) use ($filters): void {
                foreach (['p.nombre', 'p.ap', 'p.am', 't.nombre'] as $column) {
                    $names->orWhere($column, 'like', '%'.$filters['buscar'].'%');
                }
            });
        }

        return Inertia::render('incidencias', [
            'incidencias' => $query->select('incidencias.id_incidencia', 'incidencias.fecha_hecho', 'incidencias.fecha_fin', 'incidencias.estado', 'p.nombre', 'p.ap', 'p.am', 't.nombre as tipo')
                ->selectSub(DB::table('adjuntos_incidencias')->selectRaw('COUNT(*)')->whereColumn('id_incidencia', 'incidencias.id_incidencia')->where('clase', 'oficio'), 'oficios')
                ->orderByDesc('incidencias.id_incidencia')->paginate(15)->withQueryString(),
            'filters' => $filters, 'estados' => self::STATES, 'canCreate' => $request->user()->can('incidencias.registrar'),
        ]);
    }

    public function create(Request $request): Response
    {
        $filters = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'personal' => ['nullable', 'integer']]);
        $query = $this->people($request->user(), true)->with('persona:id_persona,nombre,ap,am');
        if (! empty($filters['buscar'])) {
            $query->whereHas('persona', fn (EloquentBuilder $person): EloquentBuilder => $person->where(function (EloquentBuilder $names) use ($filters): void {
                foreach (['nombre', 'ap', 'am'] as $column) {
                    $names->orWhere($column, 'like', '%'.$filters['buscar'].'%');
                }
            }));
        }
        $selected = ! empty($filters['personal']) ? $this->people($request->user(), true)->with('persona:id_persona,nombre,ap,am')->findOrFail($filters['personal']) : null;
        $people = $query->orderByDesc('id_personal')->limit(30)->get();
        if ($selected && ! $people->contains('id_personal', $selected->id_personal)) {
            $people->prepend($selected);
        }

        return Inertia::render('incidencia-form', ['personal' => $people, 'tipos' => $this->types(), 'initial' => ['id_personal' => $selected?->id_personal], 'incidenciaId' => null, 'buscar' => $filters['buscar'] ?? '']);
    }

    private function types(): Collection
    {
        return DB::table('tipos_incidencias as t')->join('categorias as c', 'c.id_categoria', '=', 't.id_categoria')
            ->whereNull('t.deleted_at')->whereNull('c.deleted_at')->select('t.id_tipo_incidencia', 't.nombre', 'c.nombre as categoria')->orderBy('c.nombre')->orderBy('t.nombre')->get();
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'id_tipo_incidencia' => ['required', 'integer', Rule::in($this->types()->pluck('id_tipo_incidencia')->all())],
            'fecha_hecho' => ['required', 'date_format:Y-m-d'], 'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_hecho'],
            'descripcion' => ['required', 'string', 'max:5000'],
        ], ['required' => 'Completa este dato.', 'in' => 'Selecciona un tipo vigente.', 'after_or_equal' => 'El término debe ser igual o posterior al inicio.']);
    }

    private function adscription(User $user, int $personal, array $data): int
    {
        $person = $this->people($user, true)->lockForUpdate()->findOrFail($personal);
        if (($person->fecha_ingreso && $data['fecha_hecho'] < $person->fecha_ingreso->format('Y-m-d')) || ($person->fecha_termino && $data['fecha_fin'] > $person->fecha_termino->format('Y-m-d'))) {
            throw ValidationException::withMessages(['fecha_hecho' => 'Las fechas deben estar dentro del periodo de participación.']);
        }
        $ads = DB::table('adscripciones_personal')->where('id_personal', $personal)->whereNull('deleted_at')
            ->where('fecha_inicio', '<=', $data['fecha_hecho'])->where(fn (Builder $dates): Builder => $dates->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $data['fecha_hecho']))->lockForUpdate()->get();
        if ($ads->count() !== 1) {
            throw ValidationException::withMessages(['fecha_hecho' => 'Debe existir una sola adscripción para la fecha inicial. Solicita al administrador revisar las adscripciones.']);
        }

        return $ads->first()->id_adscripcion;
    }

    private function history(int $id, User $user, string $before, string $after, string $note): void
    {
        DB::table('seguimientos_incidencias')->insert(['id_incidencia' => $id, 'user_id' => $user->id, 'estado_anterior' => $before, 'estado_nuevo' => $after, 'nota' => $note, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['id_personal' => ['required', 'integer']]);
        $this->people($request->user(), true)->findOrFail($request->integer('id_personal'));
        $data = $this->data($request);
        $id = DB::transaction(function () use ($request, $data): int {
            $ads = $this->adscription($request->user(), $request->integer('id_personal'), $data);
            $id = DB::table('incidencias')->insertGetId([...$data, 'id_personal' => $request->integer('id_personal'), 'id_adscripcion' => $ads, 'registrado_por' => $request->user()->id,
                'estado' => 'Registrada', 'fecha_registro' => now(), 'created_at' => now(), 'updated_at' => now()], 'id_incidencia');
            $this->history($id, $request->user(), 'Registrada', 'Registrada', 'Registro inicial.');

            return $id;
        });

        return to_route('incidencias.show', $id)->with('status', 'Incidencia registrada. Ya puedes agregar documentos y seguimiento.');
    }

    public function show(Request $request, int $id_incidencia): Response
    {
        $item = $this->incidents($request->user())->where('id_incidencia', $id_incidencia)->first();
        abort_unless($item, 404);
        $person = $this->people($request->user())->with('persona:id_persona,nombre,ap,am')->findOrFail($item->id_personal);
        $staff = $request->user()->can('incidencias.editar') && $this->people($request->user(), true)->whereKey($item->id_personal)->exists();
        $type = DB::table('tipos_incidencias')->where('id_tipo_incidencia', $item->id_tipo_incidencia)->value('nombre');
        $unit = DB::table('adscripciones_personal as a')->join('unidades_medicas as u', 'u.id_unidad', '=', 'a.id_unidad')->where('a.id_adscripcion', $item->id_adscripcion)->value('u.nom_unidad');
        $files = DB::table('adjuntos_incidencias')->where('id_incidencia', $id_incidencia)->select('id_adjunto', 'clase', 'asunto', 'numero_oficio', 'fecha_oficio', 'nombre_archivo', 'created_at')->orderByDesc('id_adjunto')->get();
        $proofs = $staff ? DB::table('archivos_expediente as a')->join('documentos_expediente as d', 'd.id_documento_expediente', '=', 'a.id_documento_expediente')
            ->where('d.id_personal', $item->id_personal)->whereNull('a.deleted_at')->whereIn('d.id_tipo_expediente', [4, 9, 10])
            ->select('a.id_archivo_expediente', 'a.nombre_archivo')->orderByDesc('a.id_archivo_expediente')->get() : [];
        unset($item->evidencia);

        return Inertia::render('incidencia', [
            'incidencia' => $item, 'personal' => $person, 'tipo' => $type, 'unidad' => $unit, 'archivos' => $files, 'expedienteArchivos' => $proofs,
            'historial' => DB::table('seguimientos_incidencias as s')->join('users as u', 'u.id', '=', 's.user_id')->where('s.id_incidencia', $id_incidencia)
                ->select('s.id_seguimiento', 's.nota', 's.estado_anterior', 's.estado_nuevo', 's.created_at', 'u.name as autor')->orderByDesc('s.id_seguimiento')->get(),
            'canManage' => $staff, 'canReview' => $request->user()->hasRole('administrador'), 'estados' => self::STATES, 'status' => $request->session()->get('status'),
        ]);
    }

    public function edit(Request $request, int $id_incidencia): Response
    {
        $item = $this->incidents($request->user(), true)->where('id_incidencia', $id_incidencia)->first();
        abort_unless($item, 404);
        abort_unless($item->estado === 'Registrada', 403);
        unset($item->evidencia);

        return Inertia::render('incidencia-form', ['initial' => $item, 'incidenciaId' => $id_incidencia,
            'personal' => $this->people($request->user(), true)->with('persona:id_persona,nombre,ap,am')->whereKey($item->id_personal)->get(), 'tipos' => $this->types(), 'buscar' => '']);
    }

    private function locked(Request $request, int $id): object
    {
        $item = $this->incidents($request->user(), true)->where('id_incidencia', $id)->lockForUpdate()->first();
        abort_unless($item, 404);
        if ((int) $item->revision !== $request->integer('revision')) {
            throw ValidationException::withMessages(['revision' => 'El registro cambió. Recarga la página antes de continuar.']);
        }

        return $item;
    }

    public function update(Request $request, int $id_incidencia): RedirectResponse
    {
        $data = $this->data($request);
        $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $id_incidencia): void {
            $item = $this->locked($request, $id_incidencia);
            abort_unless($item->estado === 'Registrada', 403);
            $ads = $this->adscription($request->user(), $item->id_personal, $data);
            DB::table('incidencias')->where('id_incidencia', $id_incidencia)->update([...$data, 'id_adscripcion' => $ads, 'revision' => $item->revision + 1, 'updated_at' => now()]);
            $previousType = DB::table('tipos_incidencias')->where('id_tipo_incidencia', $item->id_tipo_incidencia)->value('nombre');
            $this->history($id_incidencia, $request->user(), $item->estado, $item->estado, "Corrección del registro. Datos anteriores:\nTipo: ".$previousType."\nPeriodo: ".$item->fecha_hecho.' — '.($item->fecha_fin ?? $item->fecha_hecho)."\nDescripción: ".$item->descripcion);
        });

        return to_route('incidencias.show', $id_incidencia)->with('status', 'Registro corregido; se conservó el historial.');
    }

    public function followup(Request $request, int $id_incidencia): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0'], 'nota' => ['required', 'string', 'max:5000'],
            'estado' => ['required', Rule::in(self::STATES)], 'resultado' => ['nullable', 'string', 'max:5000', 'required_if:estado,Cerrada']]);
        DB::transaction(function () use ($request, $data, $id_incidencia): void {
            $item = $this->locked($request, $id_incidencia);
            $admin = $request->user()->hasRole('administrador');
            if (! $admin && ($data['estado'] !== $item->estado || ! empty($data['resultado']) || $item->estado === 'Cerrada')) {
                abort(403);
            }
            $transitions = ['Registrada' => ['Registrada', 'En revisión'], 'En revisión' => ['En revisión', 'Pendiente de documentación', 'Cerrada'], 'Pendiente de documentación' => ['Pendiente de documentación', 'En revisión'], 'Cerrada' => ['En revisión']];
            if (! in_array($data['estado'], $transitions[$item->estado] ?? ['En revisión'], true)) {
                throw ValidationException::withMessages(['estado' => 'Revisa primero la incidencia. Para cerrar debe estar En revisión; para reabrir selecciona En revisión.']);
            }
            $closing = $data['estado'] === 'Cerrada';
            DB::table('incidencias')->where('id_incidencia', $id_incidencia)->update(['estado' => $data['estado'], 'resultado' => $closing ? $data['resultado'] : null,
                'fecha_cierre' => $closing ? now() : null, 'revision' => $item->revision + 1, 'updated_at' => now()]);
            $this->history($id_incidencia, $request->user(), $item->estado, $data['estado'], $data['nota'].($closing ? "\nResultado: ".$data['resultado'] : ''));
        });

        return to_route('incidencias.show', $id_incidencia)->with('status', 'Seguimiento registrado.');
    }

    public function attachment(Request $request, int $id_incidencia): RedirectResponse
    {
        $item = $this->incidents($request->user(), true)->where('id_incidencia', $id_incidencia)->first();
        abort_unless($item, 404);
        $data = $request->validate([
            'clase' => ['required', Rule::in(['evidencia', 'oficio'])], 'asunto' => ['required', 'string', 'max:255'],
            'numero_oficio' => ['nullable', 'required_if:clase,oficio', 'string', 'max:100'], 'fecha_oficio' => ['nullable', 'required_if:clase,oficio', 'date_format:Y-m-d'],
            'archivo' => ['required', 'file', $request->input('clase') === 'oficio' ? 'mimes:pdf' : 'mimes:pdf,jpg,jpeg,png', $request->input('clase') === 'oficio' ? 'extensions:pdf' : 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ], ['archivo.max' => 'El archivo no debe superar 10 MB.', 'archivo.mimes' => 'Los oficios deben ser PDF; las evidencias pueden ser PDF, JPG o PNG.']);
        abort_if($data['clase'] === 'oficio' && ! $request->user()->hasRole('administrador'), 403);
        $file = $request->file('archivo');
        $path = $file->store((string) $id_incidencia, 'incidencias');
        try {
            DB::transaction(function () use ($request, $id_incidencia, $data, $file, $path): void {
                $item = $this->incidents($request->user(), true)->where('id_incidencia', $id_incidencia)->lockForUpdate()->first();
                abort_unless($item, 404);
                if ($item->estado === 'Cerrada' && $data['clase'] !== 'oficio') {
                    throw ValidationException::withMessages(['archivo' => 'Reabre la incidencia antes de agregar evidencia.']);
                }
                DB::table('adjuntos_incidencias')->insert(['id_incidencia' => $id_incidencia, 'user_id' => $request->user()->id, 'clase' => $data['clase'],
                    'asunto' => $data['asunto'], 'numero_oficio' => $data['clase'] === 'oficio' ? $data['numero_oficio'] : null, 'fecha_oficio' => $data['clase'] === 'oficio' ? $data['fecha_oficio'] : null,
                    'nombre_archivo' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'ruta_archivo' => $path, 'created_at' => now(), 'updated_at' => now()]);
                $this->history($id_incidencia, $request->user(), $item->estado, $item->estado, ($data['clase'] === 'oficio' ? 'Oficio disponible para el pasante: ' : 'Evidencia adjunta: ').$data['asunto']);
                DB::table('incidencias')->where('id_incidencia', $id_incidencia)->update(['revision' => $item->revision + 1, 'updated_at' => now()]);
            });
        } catch (Throwable $error) {
            Storage::disk('incidencias')->delete($path);
            throw $error;
        }

        return to_route('incidencias.show', $id_incidencia)->with('status', $data['clase'] === 'oficio' ? 'Oficio enviado a la cuenta del pasante, dentro de esta incidencia.' : 'Evidencia adjuntada.');
    }

    public function linkEvidence(Request $request, int $id_incidencia): RedirectResponse
    {
        $request->validate(['id_archivo_expediente' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $id_incidencia): void {
            $item = $this->incidents($request->user(), true)->where('id_incidencia', $id_incidencia)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_if($item->estado === 'Cerrada', 403);
            $file = DB::table('archivos_expediente as a')->join('documentos_expediente as d', 'd.id_documento_expediente', '=', 'a.id_documento_expediente')
                ->where('d.id_personal', $item->id_personal)->whereIn('d.id_tipo_expediente', [4, 9, 10])->whereNull('a.deleted_at')
                ->where('a.id_archivo_expediente', $request->integer('id_archivo_expediente'))->lockForUpdate()->select('a.*')->first();
            if (! $file) {
                throw ValidationException::withMessages(['id_archivo_expediente' => 'Selecciona un archivo vigente de comisiones, vacaciones o incidencias de este pasante.']);
            }
            if (! DB::table('adjuntos_incidencias')->where('id_incidencia', $id_incidencia)->where('id_archivo_expediente', $file->id_archivo_expediente)->exists()) {
                DB::table('adjuntos_incidencias')->insert(['id_incidencia' => $id_incidencia, 'id_archivo_expediente' => $file->id_archivo_expediente, 'user_id' => $request->user()->id,
                    'clase' => 'evidencia', 'asunto' => 'Documento del expediente', 'nombre_archivo' => $file->nombre_archivo, 'created_at' => now(), 'updated_at' => now()]);
                $this->history($id_incidencia, $request->user(), $item->estado, $item->estado, 'Se vinculó un documento del expediente: '.$file->nombre_archivo);
                DB::table('incidencias')->where('id_incidencia', $id_incidencia)->update(['revision' => $item->revision + 1, 'updated_at' => now()]);
            }
        });

        return to_route('incidencias.show', $id_incidencia)->with('status', 'Documento vinculado sin duplicar el archivo.');
    }

    public function download(Request $request, int $id_incidencia, int $id_adjunto): StreamedResponse
    {
        abort_unless($this->incidents($request->user())->where('id_incidencia', $id_incidencia)->exists(), 404);
        $file = DB::table('adjuntos_incidencias')->where('id_incidencia', $id_incidencia)->where('id_adjunto', $id_adjunto)->first();
        abort_unless($file, 404);
        $disk = 'incidencias';
        $path = $file->ruta_archivo;
        if ($file->id_archivo_expediente) {
            $path = DB::table('archivos_expediente')->where('id_archivo_expediente', $file->id_archivo_expediente)->whereNull('deleted_at')->value('ruta_archivo');
            $disk = 'expedientes';
        }
        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $file->nombre_archivo, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
