<?php

namespace App\Http\Controllers;

use App\Models\PersonalFormacion;
use App\ProgramacionProductividad;
use App\Promociones;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProgramacionProductividadController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DB::table('programacion_productividad as pp')->join('promociones as pr', 'pr.id_promocion', '=', 'pp.id_promocion')
            ->join('carreras as c', 'c.id_carrera', '=', 'pp.id_carrera')->join('unidades_medicas as u', 'u.id_unidad', '=', 'pp.id_unidad');
        $filters = $request->validate(['promocion' => ['nullable', Rule::in(Promociones::selections($request))]]);
        $selected = (string) ($filters['promocion'] ?? Promociones::selection($request));
        $request->session()->put(Promociones::sessionKey($request), $selected);
        $extended = DB::table('personal_formacion')->whereIn('id_personal', Promociones::extended())->select('id_promocion');
        if ($selected === 'vigentes') {
            $query->where(fn (Builder $q): Builder => $q->where('pr.estado', 'En curso')->orWhereIn('pp.id_promocion', $extended));
        } elseif ($selected === 'prorrogas') {
            $query->whereIn('pp.id_promocion', $extended);
        } elseif ($selected === 'sin') {
            $query->whereRaw('1 = 0');
        } elseif ($selected !== 'todas') {
            $query->where('pp.id_promocion', (int) $selected);
        }

        return Inertia::render('productividad-programacion', [
            'programaciones' => $query->select('pp.*', 'pr.nombre as promocion', 'c.nombre as carrera', 'u.nom_unidad as unidad')
                ->selectSub(DB::table('productividad')->selectRaw('COUNT(*)')->whereColumn('id_programacion', 'pp.id_programacion'), 'capturas')
                ->orderByDesc('pp.id_programacion')->paginate(20)->withQueryString(),
        ]);
    }

    public function form(?int $id_programacion = null): Response
    {
        $row = $id_programacion ? DB::table('programacion_productividad')->where('id_programacion', $id_programacion)->first() : null;
        abort_if($id_programacion && ! $row, 404);

        return Inertia::render('productividad-programacion-form', [
            'programacion' => $row,
            'locked' => $row && $this->used($id_programacion),
            'promociones' => DB::table('promociones')->whereNull('deleted_at')->orderByDesc('fecha_inicio')->get(['id_promocion', 'nombre', 'fecha_inicio', 'fecha_fin']),
            'carreras' => DB::table('promocion_carrera as pc')->join('carreras as c', 'c.id_carrera', '=', 'pc.id_carrera')->whereNull('c.deleted_at')->get(['pc.id_promocion', 'c.id_carrera', 'c.nombre']),
            'unidades' => DB::table('unidades_medicas')->whereNull('deleted_at')->orderBy('nom_unidad')->get(['id_unidad', 'nom_unidad']),
            'actividades' => DB::table('tipos_consultas')->whereNull('deleted_at')->orderBy('nombre')->get(['id_tipo_consulta', 'nombre', 'id_carrera']),
            'categorias' => DB::table('categorias_productividad')->whereNull('deleted_at')->orderBy('nombre')->get(),
            'medidas' => DB::table('unidades_medida_productividad')->whereNull('deleted_at')->orderBy('nombre')->get(),
            'personas' => $row ? ProgramacionProductividad::forPeople(PersonalFormacion::query()->whereHas('persona')->pluck('id_personal')->all())
                ->where('pp.id_programacion', $id_programacion)->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')
                ->select('pf.id_personal', 'p.nombre', 'p.ap', 'p.am')->get() : [],
            'metas' => $row ? DB::table('metas_productividad_personal')->where('id_programacion', $id_programacion)->get() : [],
        ]);
    }

    private function used(int $id): bool
    {
        return DB::table('productividad')->where('id_programacion', $id)->exists()
            || DB::table('metas_productividad_personal')->where('id_programacion', $id)->exists();
    }

    public function save(Request $request, ?int $id_programacion = null): RedirectResponse
    {
        $data = $request->validate([
            'id_promocion' => ['required', 'integer'], 'id_carrera' => ['required', 'integer'], 'id_unidad' => ['required', 'integer'],
            'id_tipo_consulta' => ['required', 'integer'], 'id_categoria_productividad' => ['required', 'integer'], 'id_unidad_medida' => ['required', 'integer'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'], 'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'meta_unidad' => ['nullable', 'integer', 'min:0', 'max:100000000'], 'revision' => [$id_programacion ? 'required' : 'nullable', 'integer', 'min:0'],
        ], ['required' => 'Completa este dato.', 'after_or_equal' => 'El término debe ser igual o posterior al inicio.']);
        DB::transaction(function () use ($data, $id_programacion): void {
            $promotion = DB::table('promociones')->where('id_promocion', $data['id_promocion'])->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $promotion || $data['fecha_inicio'] < $promotion->fecha_inicio) {
                throw ValidationException::withMessages(['id_promocion' => 'Selecciona una promoción vigente y un periodo posterior a su inicio.']);
            }
            $current = $id_programacion ? DB::table('programacion_productividad')->where('id_programacion', $id_programacion)->lockForUpdate()->first() : null;
            abort_if($id_programacion && ! $current, 404);
            if ($current && ($current->revision !== (int) $data['revision'] || $this->used($id_programacion))) {
                throw ValidationException::withMessages(['revision' => 'La programación cambió o ya tiene capturas o metas individuales. Sus datos históricos se conservan.']);
            }
            if (! DB::table('promocion_carrera')->where('id_promocion', $data['id_promocion'])->where('id_carrera', $data['id_carrera'])->exists()) {
                throw ValidationException::withMessages(['id_carrera' => 'La carrera no está incluida en esta promoción.']);
            }
            $references = [];
            foreach (['id_carrera' => 'carreras', 'id_unidad' => 'unidades_medicas', 'id_tipo_consulta' => 'tipos_consultas', 'id_categoria_productividad' => 'categorias_productividad', 'id_unidad_medida' => 'unidades_medida_productividad'] as $key => $table) {
                $references[$key] = DB::table($table)->where($key, $data[$key])->whereNull('deleted_at')->lockForUpdate()->first();
                if (! $references[$key]) {
                    throw ValidationException::withMessages([$key => 'Selecciona una opción vigente.']);
                }
            }
            $type = $references['id_tipo_consulta'];
            if ($type->id_carrera && $type->id_carrera !== (int) $data['id_carrera']) {
                throw ValidationException::withMessages(['id_tipo_consulta' => 'La actividad pertenece a otra carrera.']);
            }
            $overlap = DB::table('programacion_productividad')->where('id_promocion', $data['id_promocion'])->where('id_carrera', $data['id_carrera'])
                ->where('id_unidad', $data['id_unidad'])->where('id_tipo_consulta', $data['id_tipo_consulta'])
                ->where('fecha_inicio', '<=', $data['fecha_fin'])->where('fecha_fin', '>=', $data['fecha_inicio']);
            if ($id_programacion) {
                $overlap->where('id_programacion', '!=', $id_programacion);
            }
            if ($overlap->exists()) {
                throw ValidationException::withMessages(['fecha_inicio' => 'Esta actividad ya está programada para la misma promoción, carrera, unidad y periodo.']);
            }
            $values = collect($data)->except('revision')->all();
            $values += ['actividad' => $type->nombre, 'categoria' => $references['id_categoria_productividad']->nombre,
                'unidad_medida' => $references['id_unidad_medida']->nombre, 'revision' => ($current?->revision ?? -1) + 1, 'updated_at' => now()];
            if ($current) {
                DB::table('programacion_productividad')->where('id_programacion', $id_programacion)->update($values);
            } else {
                DB::table('programacion_productividad')->insert($values + ['created_at' => now()]);
            }
        });

        return to_route('productividad.programacion.index', ['promocion' => $data['id_promocion']])->with('success', 'Actividad programada.');
    }

    public function toggle(Request $request, int $id_programacion): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'activa' => ['required', 'boolean']]);
        $changed = DB::table('programacion_productividad')->where('id_programacion', $id_programacion)->where('revision', $data['revision'])
            ->update(['activa' => $data['activa'], 'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
        if (! $changed) {
            throw ValidationException::withMessages(['revision' => 'La programación cambió. Recarga la página.']);
        }

        return back();
    }

    public function goal(Request $request, int $id_programacion): RedirectResponse
    {
        $data = $request->validate(['id_personal' => ['required', 'integer'], 'cantidad' => ['required', 'integer', 'min:0', 'max:100000000']]);
        DB::transaction(function () use ($data, $id_programacion): void {
            $person = PersonalFormacion::query()->whereHas('persona')->lockForUpdate()->findOrFail($data['id_personal']);
            $row = DB::table('programacion_productividad')->where('id_programacion', $id_programacion)->lockForUpdate()->first();
            abort_unless($row, 404);
            if (! ProgramacionProductividad::forPeople([$person->id_personal])->where('pp.id_programacion', $id_programacion)->exists()) {
                throw ValidationException::withMessages(['id_personal' => 'La participación no corresponde a esta promoción, carrera, unidad y periodo.']);
            }
            $key = ['id_programacion' => $id_programacion, 'id_personal' => $person->id_personal];
            if (DB::table('metas_productividad_personal')->where($key)->exists()) {
                throw ValidationException::withMessages(['cantidad' => 'La meta individual ya está definida para este periodo y se conserva como historial.']);
            }
            DB::table('metas_productividad_personal')->insert($key + ['cantidad' => $data['cantidad'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Meta individual registrada.');
    }
}
