<?php

namespace App\Http\Controllers;

use App\Promociones;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PromocionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['estado' => ['nullable', Rule::in(['Programada', 'En curso', 'Cerrada', 'Todas'])], 'anio' => ['nullable', 'integer', 'between:2000,2100']]);
        $state = $filters['estado'] ?? 'En curso';
        $year = (int) ($filters['anio'] ?? now()->year);
        $query = DB::table('promociones')->whereNull('deleted_at');
        if ($state !== 'Todas') {
            $query->where('estado', $state);
        }
        $query->select('promociones.*')->selectSub(DB::table('personal_formacion')->selectRaw('COUNT(*)')->whereColumn('id_promocion', 'promociones.id_promocion')->whereNull('deleted_at'), 'participantes');

        return Inertia::render('promociones', [
            'promociones' => $query->orderByDesc('fecha_inicio')->paginate(12)->withQueryString(),
            'calendario' => DB::table('promociones')->whereNull('deleted_at')->where('fecha_inicio', '<=', $year.'-12-31')->where('fecha_fin', '>=', $year.'-01-01')->orderBy('fecha_inicio')->get(['id_promocion', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado']),
            'filters' => ['estado' => $state, 'anio' => $year],
            'sinPromocion' => DB::table('personal_formacion')->whereNull('deleted_at')->whereNull('id_promocion')->count(),
            'periodosExtendidos' => Promociones::extended()->count(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(): Response
    {
        return $this->form();
    }

    public function edit(int $id_promocion): Response
    {
        return $this->form($id_promocion);
    }

    private function form(?int $id = null): Response
    {
        $row = $id ? DB::table('promociones')->where('id_promocion', $id)->whereNull('deleted_at')->first() : null;
        abort_if($id && ! $row, 404);

        return Inertia::render('promocion-form', [
            'initial' => $row ? [...(array) $row, 'carreras' => DB::table('promocion_carrera')->where('id_promocion', $id)->pluck('id_carrera')] : null,
            'carreras' => DB::table('carreras')->whereNull('deleted_at')->orderBy('nombre')->get(['id_carrera', 'nombre']),
        ]);
    }

    public function show(Request $request, int $id_promocion): Response
    {
        $row = DB::table('promociones')->where('id_promocion', $id_promocion)->whereNull('deleted_at')->first();
        abort_unless($row, 404);
        $people = DB::table('personal_formacion as pf')->join('personas as p', 'p.id_persona', '=', 'pf.id_persona')
            ->where('pf.id_promocion', $id_promocion)->whereNull('pf.deleted_at')->whereNull('p.deleted_at');

        return Inertia::render('promocion-detail', [
            'promocion' => $row,
            'carreras' => DB::table('promocion_carrera as pc')->join('carreras as c', 'c.id_carrera', '=', 'pc.id_carrera')->where('pc.id_promocion', $id_promocion)->orderBy('c.nombre')->get(['c.id_carrera', 'c.nombre']),
            'participaciones' => $people->select('pf.id_personal', 'p.nombre', 'p.ap', 'p.am', 'pf.fecha_ingreso', 'pf.fecha_termino', 'pf.estado', 'pf.motivo_periodo')->orderBy('p.ap')->orderBy('pf.id_personal')->paginate(20),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id_promocion): RedirectResponse
    {
        return $this->save($request, $id_promocion);
    }

    private function save(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150', Rule::unique('promociones', 'nombre')->ignore($id, 'id_promocion')],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'estado' => ['required', Rule::in(['Programada', 'En curso', 'Cerrada'])],
            'permite_inscripciones' => ['required', 'boolean'],
            'revision' => [$id ? 'required' : 'nullable', 'integer', 'min:0'],
            'carreras' => ['required', 'array', 'min:1'],
            'carreras.*' => ['required', 'integer', 'distinct', Rule::exists('carreras', 'id_carrera')->whereNull('deleted_at')],
        ], ['required' => 'Completa este dato.', 'unique' => 'Ya existe una promoción con este nombre.', 'after_or_equal' => 'El término no puede ser anterior al inicio.', 'carreras.required' => 'Selecciona al menos una carrera.', 'exists' => 'Selecciona una carrera vigente.']);
        try {
            $id = DB::transaction(function () use ($id, $data): int {
                $row = $id ? DB::table('promociones')->where('id_promocion', $id)->whereNull('deleted_at')->lockForUpdate()->first() : null;
                abort_if($id && ! $row, 404);
                if ($row && (int) $row->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['revision' => 'Otra persona modificó la promoción. Vuelve a abrirla antes de guardar.']);
                }
                if (DB::table('carreras')->whereIn('id_carrera', $data['carreras'])->whereNull('deleted_at')->lockForUpdate()->get()->count() !== count($data['carreras'])) {
                    throw ValidationException::withMessages(['carreras' => 'Alguna carrera dejó de estar disponible.']);
                }
                if ($id && DB::table('personal_formacion')->where('id_promocion', $id)->exists()) {
                    if ($row->fecha_inicio !== $data['fecha_inicio'] || $row->fecha_fin !== $data['fecha_fin']) {
                        throw ValidationException::withMessages(['fecha_inicio' => 'Con participantes registrados se conserva el periodo original. Ajusta el periodo individual del pasante si necesita una prórroga.']);
                    }
                    $used = DB::table('datos_academicos as d')->join('personal_formacion as pf', 'pf.id_personal', '=', 'd.id_personal')->where('pf.id_promocion', $id)->whereNotIn('d.id_carrera', $data['carreras'])->exists();
                    if ($used) {
                        throw ValidationException::withMessages(['carreras' => 'No puedes quitar una carrera que tiene participaciones en esta promoción.']);
                    }
                }
                $values = array_intersect_key($data, array_flip(['nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'permite_inscripciones']));
                $values['permite_inscripciones'] = $data['estado'] === 'Cerrada' ? false : $data['permite_inscripciones'];
                $values['updated_at'] = now();
                $values['revision'] = ($row?->revision ?? 0) + 1;
                if ($id) {
                    DB::table('promociones')->where('id_promocion', $id)->update($values);
                } else {
                    $id = DB::table('promociones')->insertGetId([...$values, 'created_at' => now()]);
                }
                DB::table('promocion_carrera')->where('id_promocion', $id)->delete();
                DB::table('promocion_carrera')->insert(array_map(fn ($career): array => ['id_promocion' => $id, 'id_carrera' => $career], $data['carreras']));

                return $id;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una promoción con este nombre.']);
        }

        return to_route('promociones.show', $id)->with('status', 'Promoción guardada. Las fechas y el estado individual de los pasantes se conservan.');
    }
}
