<?php

namespace App\Http\Controllers;

use App\Models\PersonalFormacion;
use App\Models\User;
use App\ProgramacionProductividad;
use App\Promociones;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductividadController extends Controller
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
            if (! $review && in_array('productividad', $modules['pasante'] ?? [], true)) {
                $scope->orWhere('id_persona', $user->id_persona ?? 0);
            }
            if (in_array('productividad', $modules['asesor'] ?? [], true)) {
                $scope->orWhereHas('asignaciones', fn (EloquentBuilder $assignments): EloquentBuilder => $assignments->vigentes()
                    ->whereHas('asesor', fn (EloquentBuilder $advisors): EloquentBuilder => $advisors->where('id_persona', $user->id_persona ?? 0)));
            }
        });
    }

    private function records(User $user): Builder
    {
        return DB::table('productividad')->whereNull('productividad.deleted_at')
            ->whereIn('productividad.id_personal', $this->people($user)->select('id_personal'));
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['semana' => ['nullable', 'date_format:Y-m-d'], 'personal' => ['nullable', 'integer']]);
        $start = CarbonImmutable::parse($filters['semana'] ?? today()->toDateString())->startOfWeek();
        $end = $start->addDays(6);
        $people = $this->people($request->user());
        Promociones::filter($request, $people, 'personal_formacion.id_promocion');
        $participants = $people->with('persona:id_persona,nombre,ap,am')->orderBy('id_personal')->get();
        $query = $this->records($request->user())->whereIn('productividad.id_personal', $participants->modelKeys())
            ->whereBetween('fecha_actividad', [$start->toDateString(), $end->toDateString()]);
        if (! empty($filters['personal'])) {
            $query->where('productividad.id_personal', $filters['personal']);
        }
        $summary = (clone $query)->join('tipos_consultas as t', 't.id_tipo_consulta', '=', 'productividad.id_tipo_consulta')
            ->leftJoin('carreras as c', 'c.id_carrera', '=', 't.id_carrera')
            ->leftJoin('programacion_productividad as pp', 'pp.id_programacion', '=', 'productividad.id_programacion')
            ->select('t.id_tipo_consulta', 'c.nombre as carrera', 'productividad.estado', 'pp.unidad_medida')
            ->selectRaw('COALESCE(pp.actividad, productividad.actividad_snapshot, t.nombre) as nombre, SUM(num_actividades) as cantidad')
            ->groupBy('t.id_tipo_consulta', 'c.nombre', 'productividad.estado', 'pp.id_programacion', 'pp.unidad_medida')
            ->groupByRaw('COALESCE(pp.actividad, productividad.actividad_snapshot, t.nombre)')->get();
        $records = $query->join('personal_formacion as pf', 'pf.id_personal', '=', 'productividad.id_personal')
            ->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')
            ->join('tipos_consultas as t', 't.id_tipo_consulta', '=', 'productividad.id_tipo_consulta')
            ->leftJoin('programacion_productividad as pp', 'pp.id_programacion', '=', 'productividad.id_programacion')
            ->select('productividad.*', 'p.nombre', 'p.ap', 'p.am', 'pf.id_persona', 'pp.unidad_medida')
            ->selectRaw('COALESCE(pp.actividad, productividad.actividad_snapshot, t.nombre) as actividad')
            ->orderByDesc('fecha_actividad')->orderByDesc('id_productividad')->paginate(20)->withQueryString();
        $user = $request->user();
        $isAdmin = $user->hasRole('administrador');
        $isTrainee = $user->hasRole('pasante');
        $canEdit = $user->can('productividad.editar');
        $canDelete = $user->can('productividad.eliminar');
        $canReview = $user->can('productividad.revisar');
        $reviewIds = $this->people($user, true)->pluck('id_personal')->all();
        $records->through(function (object $record) use ($user, $reviewIds, $isAdmin, $isTrainee, $canEdit, $canDelete, $canReview): object {
            $owner = $isAdmin || ($isTrainee && $record->id_persona === $user->id_persona);
            $record->canEdit = $owner && $canEdit && $record->estado !== 'Validada';
            $record->canDelete = $owner && $canDelete && $record->estado !== 'Validada';
            $record->canReview = $canReview && in_array($record->id_personal, $reviewIds)
                && ($isAdmin || $record->id_persona !== $user->id_persona);
            unset($record->evidencia);

            return $record;
        });

        return Inertia::render('productividad', [
            'registros' => $records, 'resumen' => $summary,
            'semana' => $start->toDateString(), 'finSemana' => $end->toDateString(), 'personal' => $filters['personal'] ?? '',
            'participaciones' => $participants->map(fn (PersonalFormacion $person): array => [
                'id_personal' => $person->id_personal, 'nombre' => trim($person->persona->nombre.' '.$person->persona->ap.' '.$person->persona->am),
                'estado' => $person->estado,
            ]),
            'canCreate' => $user->can('productividad.registrar') && ($user->hasRole('administrador') || $user->hasRole('pasante')),
            'canCatalog' => $user->hasRole('administrador') && $user->can('catalogos.ver'),
        ]);
    }

    public function form(Request $request, ?int $id_productividad = null): Response
    {
        $record = $id_productividad ? $this->records($request->user())->where('id_productividad', $id_productividad)->first() : null;
        abort_if($id_productividad && ! $record, 404);
        if ($record) {
            $person = $this->people($request->user())->findOrFail($record->id_personal);
            $this->assertWriter($request->user(), $person);
            abort_if($record->estado === 'Validada', 403);
            unset($record->evidencia);
        }
        $people = $this->people($request->user());
        if (! $request->user()->hasRole('administrador')) {
            $people->where('id_persona', $request->user()->id_persona ?? 0);
            if (! $record) {
                Promociones::filter($request, $people);
                $people->orderByDesc('fecha_ingreso')->orderByDesc('id_personal')->limit(1);
            }
        }
        if ($record) {
            $people->whereKey($record->id_personal);
        }
        $people->where('estado', 'En formacion');
        $participants = $people->with('persona:id_persona,nombre,ap,am')->get();
        $academic = DB::table('datos_academicos')->whereNull('deleted_at')->whereIn('id_personal', $participants->modelKeys())->get(['id_personal', 'id_carrera']);

        return Inertia::render('productividad-form', [
            'registro' => $record,
            'canSelectPersonal' => $request->user()->hasRole('administrador') && ! $record,
            'participaciones' => $participants->map(fn (PersonalFormacion $person): array => [
                'id_personal' => $person->id_personal, 'nombre' => trim($person->persona->nombre.' '.$person->persona->ap.' '.$person->persona->am),
                'fecha_ingreso' => $person->fecha_ingreso?->toDateString(), 'fecha_termino' => $person->fecha_termino?->toDateString(),
                'carreras' => $academic->where('id_personal', $person->id_personal)->pluck('id_carrera')->values(),
                'id_promocion' => $person->id_promocion,
            ]),
            'programaciones' => ProgramacionProductividad::forPeople($participants->modelKeys())->get(),
            'adscripciones' => DB::table('adscripciones_personal')->whereIn('id_personal', $participants->modelKeys())->whereNull('deleted_at')->get(['id_personal', 'id_unidad', 'fecha_inicio', 'fecha_fin']),
            'actividades' => DB::table('tipos_consultas')->whereNull('deleted_at')->orderBy('nombre')->get(['id_tipo_consulta', 'nombre', 'id_carrera']),
            'hoy' => today()->toDateString(),
        ]);
    }

    public function report(Request $request, int $id_personal): Response
    {
        $person = $this->people($request->user())->with('persona')->findOrFail($id_personal);
        $records = $this->records($request->user())->where('id_personal', $id_personal)->orderBy('fecha_actividad')->get();
        $applicable = ProgramacionProductividad::forPeople([$id_personal])->pluck('pp.id_programacion');
        $ids = $applicable->merge($records->pluck('id_programacion')->filter())->unique();
        $goals = DB::table('metas_productividad_personal')->where('id_personal', $id_personal)->get()->keyBy('id_programacion');
        $programs = DB::table('programacion_productividad as pp')->join('unidades_medicas as u', 'u.id_unidad', '=', 'pp.id_unidad')
            ->whereIn('pp.id_programacion', $ids)->select('pp.*', 'u.nom_unidad')->orderBy('pp.categoria')->orderBy('pp.actividad')->get();
        $rows = $programs->map(function (object $program) use ($records, $goals): array {
            $items = $records->where('id_programacion', $program->id_programacion);
            $validated = (int) $items->where('estado', 'Validada')->sum('num_actividades');
            $goal = $goals->get($program->id_programacion)?->cantidad;

            return [
                'id_programacion' => $program->id_programacion, 'actividad' => $program->actividad,
                'categoria' => $program->categoria, 'unidad_medida' => $program->unidad_medida, 'unidad' => $program->nom_unidad,
                'fecha_inicio' => $program->fecha_inicio, 'fecha_fin' => $program->fecha_fin,
                'meta_unidad' => $program->meta_unidad, 'meta_individual' => $goal,
                'realizadas' => (int) $items->sum('num_actividades'), 'validadas' => $validated,
                'avance_individual' => $goal > 0 ? round($validated * 100 / $goal, 2) : null,
                'aportacion_unidad' => $program->meta_unidad > 0 ? round($validated * 100 / $program->meta_unidad, 2) : null,
                'registros' => $items->count(), 'sin_actividad' => $items->where('resultado', 'Sin actividad')->count(),
                'no_aplica' => $items->where('resultado', 'No aplica')->count(),
                'observaciones' => $items->filter(fn (object $item): bool => (bool) ($item->descripcion || $item->observaciones))
                    ->map(fn (object $item): array => ['fecha' => $item->fecha_actividad, 'resultado' => $item->resultado, 'descripcion' => $item->descripcion, 'revision' => $item->observaciones])->values(),
            ];
        });

        return Inertia::render('productividad-resumen', [
            'persona' => ['nombre' => trim($person->persona->nombre.' '.$person->persona->ap.' '.$person->persona->am), 'id_personal' => $id_personal],
            'filas' => $rows,
            'historicos' => $records->whereNull('id_programacion')->groupBy(fn (object $r): string => (string) ($r->actividad_snapshot ?? 'Actividad #'.$r->id_tipo_consulta))
                ->map(fn ($items, string $name): array => ['actividad' => $name, 'cantidad' => (int) $items->sum('num_actividades')])->values(),
        ]);
    }

    private function assertWriter(User $user, PersonalFormacion $person): void
    {
        abort_unless($user->hasRole('administrador') || ($user->hasRole('pasante') && $user->id_persona === $person->id_persona), 403);
    }

    public function save(Request $request, ?int $id_productividad = null): RedirectResponse
    {
        $data = $request->validate([
            'id_personal' => ['required', 'integer'], 'id_tipo_consulta' => ['required', 'integer'],
            'id_programacion' => ['nullable', 'integer'], 'resultado' => ['sometimes', Rule::in(['Realizada', 'Sin actividad', 'No aplica'])],
            'fecha_actividad' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'num_actividades' => ['required', 'integer', 'min:0', 'max:100000'],
            'descripcion' => ['nullable', 'string', 'max:3000'], 'revision' => [$id_productividad ? 'required' : 'nullable', 'integer', 'min:0'],
        ]);
        $data['resultado'] ??= 'Realizada';
        $data['id_programacion'] = empty($data['id_programacion']) ? null : (int) $data['id_programacion'];
        if (($data['resultado'] === 'Realizada' && (int) $data['num_actividades'] < 1) || ($data['resultado'] !== 'Realizada' && (int) $data['num_actividades'] !== 0)) {
            throw ValidationException::withMessages(['num_actividades' => 'Registra una cantidad positiva para actividades realizadas; para «Sin actividad» o «No aplica», registra cero.']);
        }
        if ($data['resultado'] !== 'Realizada' && empty(trim($data['descripcion'] ?? ''))) {
            throw ValidationException::withMessages(['descripcion' => 'Explica por qué no se realizó la actividad o por qué no aplica.']);
        }
        DB::transaction(function () use ($request, $data, $id_productividad): void {
            $person = $this->people($request->user())->lockForUpdate()->findOrFail($data['id_personal']);
            $this->assertWriter($request->user(), $person);
            $record = $id_productividad ? $this->records($request->user())->where('id_productividad', $id_productividad)->lockForUpdate()->first() : null;
            abort_if($id_productividad && (! $record || $record->id_personal !== $person->id_personal), 404);
            if ($record) {
                $this->checkRevision($record, (int) $data['revision']);
                abort_if($record->estado === 'Validada', 403);
                if ($record->id_programacion !== $data['id_programacion']) {
                    throw ValidationException::withMessages(['id_programacion' => 'La captura conserva su programación original.']);
                }
            }
            if ($person->estado !== 'En formacion' || ! $person->fecha_ingreso || $data['fecha_actividad'] < $person->fecha_ingreso->toDateString()
                || ($person->fecha_termino && $data['fecha_actividad'] > $person->fecha_termino->toDateString())) {
                throw ValidationException::withMessages(['fecha_actividad' => 'La actividad debe estar dentro del periodo de una participación en formación.']);
            }
            $program = null;
            if ($data['id_programacion']) {
                $program = ProgramacionProductividad::forPeople([$person->id_personal], $data['fecha_actividad'])
                    ->where('pp.id_programacion', $data['id_programacion'])->lockForUpdate()->first();
                if (! $program || (! $record && ! $program->activa) || $program->id_tipo_consulta !== (int) $data['id_tipo_consulta']) {
                    throw ValidationException::withMessages(['id_programacion' => 'La actividad no está habilitada para esta promoción, carrera, adscripción y fecha.']);
                }
            } elseif ($person->id_promocion && ! $record) {
                throw ValidationException::withMessages(['id_programacion' => 'Selecciona una actividad programada por Enseñanza para tu promoción.']);
            }
            $activity = DB::table('tipos_consultas')->where('id_tipo_consulta', $data['id_tipo_consulta'])->lockForUpdate()->first();
            if (! $activity || (! $program && ($activity->deleted_at || ($activity->id_carrera && ! DB::table('datos_academicos')->where('id_personal', $person->id_personal)->where('id_carrera', $activity->id_carrera)->whereNull('deleted_at')->exists())))) {
                throw ValidationException::withMessages(['id_tipo_consulta' => 'Selecciona una actividad vigente que corresponda a tu carrera.']);
            }
            $duplicate = DB::table('productividad')->whereNull('deleted_at')->where('id_personal', $person->id_personal)
                ->where('id_tipo_consulta', $data['id_tipo_consulta'])->where('fecha_actividad', $data['fecha_actividad']);
            if ($record) {
                $duplicate->where('id_productividad', '!=', $id_productividad);
            }
            if ($duplicate->exists()) {
                throw ValidationException::withMessages(['fecha_actividad' => 'Ya registraste esta actividad en esa fecha. Edita el registro existente para ajustar su cantidad.']);
            }
            $values = collect($data)->only(['id_personal', 'id_tipo_consulta', 'fecha_actividad', 'num_actividades', 'descripcion', 'id_programacion', 'resultado'])->all();
            $values['actividad_snapshot'] = $program?->actividad ?? (($record && $record->id_tipo_consulta === (int) $data['id_tipo_consulta']) ? ($record->actividad_snapshot ?? $activity->nombre) : $activity->nombre);
            $values += ['estado' => 'Registrada', 'observaciones' => null, 'revisado_por' => null, 'fecha_revision' => null, 'revision' => $record ? $record->revision + 1 : 0, 'updated_at' => now()];
            if ($record) {
                DB::table('productividad')->where('id_productividad', $id_productividad)->update($values);
            } else {
                DB::table('productividad')->insert($values + ['fecha_registro' => now(), 'created_at' => now()]);
            }
        });

        return to_route('productividad.index', ['semana' => $data['fecha_actividad'], 'personal' => $data['id_personal']])->with('success', 'Actividad guardada.');
    }

    private function checkRevision(object $record, int $revision): void
    {
        if ($record->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => 'El registro cambió. Recarga la página antes de continuar.']);
        }
    }

    public function review(Request $request, int $id_productividad): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', Rule::in(['Validada', 'Por corregir'])],
            'observaciones' => ['required_if:estado,Por corregir', 'nullable', 'string', 'max:3000'], 'revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $id_productividad): void {
            $record = $this->records($request->user())->where('id_productividad', $id_productividad)->lockForUpdate()->first();
            abort_unless($record, 404);
            $person = $this->people($request->user(), true)->findOrFail($record->id_personal);
            abort_unless($request->user()->hasRole('administrador') || $person->id_persona !== $request->user()->id_persona, 403);
            $this->checkRevision($record, (int) $data['revision']);
            DB::table('productividad')->where('id_productividad', $id_productividad)->update([
                'estado' => $data['estado'], 'observaciones' => $data['observaciones'] ?? null, 'revisado_por' => $request->user()->id,
                'fecha_revision' => now(), 'revision' => $record->revision + 1, 'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Revisión guardada.');
    }

    public function destroy(Request $request, int $id_productividad): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $id_productividad): void {
            $record = $this->records($request->user())->where('id_productividad', $id_productividad)->lockForUpdate()->first();
            abort_unless($record, 404);
            $this->assertWriter($request->user(), $this->people($request->user())->findOrFail($record->id_personal));
            $this->checkRevision($record, (int) $data['revision']);
            abort_if($record->estado === 'Validada', 403);
            DB::table('productividad')->where('id_productividad', $id_productividad)->update(['deleted_at' => now(), 'updated_at' => now(), 'revision' => $record->revision + 1]);
        });

        return back()->with('success', 'Registro eliminado.');
    }
}
