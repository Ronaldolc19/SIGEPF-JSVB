<?php

namespace App\Http\Controllers;

use App\Catalogo;
use App\Http\Requests\SavePersonalFormacionRequest;
use App\Models\PersonalFormacion;
use App\Promociones;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonalFormacionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PersonalFormacion::class);

        $query = PersonalFormacion::query()->visibleTo($request->user());
        Promociones::filter($request, $query);

        return Inertia::render('personal-records', [
            'participaciones' => $query
                ->with(['persona:id_persona,nombre,ap,am', 'tipo:id_tipo_personal,nombre'])
                ->orderByDesc('id_personal')->paginate(20)->withQueryString(),
            'detail' => null,
            'linked' => $request->user()->persona()->exists(),
            'isAdmin' => $request->user()->hasRole('administrador'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function show(Request $request, int $id_personal): Response
    {
        Gate::authorize('viewAny', PersonalFormacion::class);
        $personal = PersonalFormacion::query()->visibleTo($request->user())->whereKey($id_personal)
            ->with(['persona:id_persona,nombre,ap,am', 'tipo:id_tipo_personal,nombre'])->firstOrFail();
        Gate::authorize('view', $personal);

        return Inertia::render('personal-records', [
            'participaciones' => null,
            'detail' => $personal,
            'promocion' => $personal->id_promocion ? DB::table('promociones')->where('id_promocion', $personal->id_promocion)->first(['nombre', 'fecha_inicio', 'fecha_fin', 'estado']) : null,
            'historialPeriodos' => DB::table('historial_periodos_personal as h')->join('users as u', 'u.id', '=', 'h.id_usuario')->where('h.id_personal', $id_personal)->orderByDesc('h.id_historial_periodo')->get(['h.*', 'u.name as responsable']),
            'academicos' => $this->academicos($id_personal),
            'adscripciones' => $this->adscripciones($id_personal),
            'status' => $request->session()->get('status'),
            'linked' => $request->user()->persona()->exists(),
            'isAdmin' => $request->user()->hasRole('administrador'),
        ]);
    }

    public function edit(int $id_personal): Response
    {
        $personal = PersonalFormacion::query()->whereHas('persona')->with('persona')->findOrFail($id_personal);

        return Inertia::render('personal-form', [
            'personal' => $personal,
            'academicos' => DB::table('datos_academicos')->where('id_personal', $id_personal)->whereNull('deleted_at')->first(),
        ]);
    }

    public function update(SavePersonalFormacionRequest $request, int $id_personal): RedirectResponse
    {
        DB::transaction(function () use ($request, $id_personal): void {
            $personal = PersonalFormacion::query()->whereHas('persona')->lockForUpdate()->findOrFail($id_personal);
            $data = $request->validated();
            $beforeStart = $personal->fecha_ingreso?->format('Y-m-d');
            $beforeEnd = $personal->fecha_termino?->format('Y-m-d');
            Promociones::validateParticipation($personal, $personal->id_promocion, (int) $data['id_carrera'], $data['fecha_ingreso'], $data['fecha_termino'] ?? null, $data['motivo_periodo'] ?? $personal->motivo_periodo);
            foreach (['id_tipo_personal' => 'tipos_personal', 'id_institucion' => 'instituciones', 'id_carrera' => 'carreras', 'id_nivel_academico' => 'nivel_academico'] as $key => $table) {
                if (! Catalogo::query($table)->where($key, $data[$key])->lockForUpdate()->first()) {
                    throw ValidationException::withMessages([$key => 'Selecciona una opción vigente.']);
                }
            }
            $outside = DB::table('adscripciones_personal')->where('id_personal', $id_personal)->whereNull('deleted_at')
                ->where(function (Builder $query) use ($data): void {
                    $query->where('fecha_inicio', '<', $data['fecha_ingreso']);
                    if (! empty($data['fecha_termino'])) {
                        $query->orWhere('fecha_inicio', '>', $data['fecha_termino'])->orWhere('fecha_fin', '>', $data['fecha_termino'])->orWhereNull('fecha_fin');
                    }
                })->exists();
            if ($outside) {
                throw ValidationException::withMessages(['fecha_termino' => 'El periodo debe contener todas las adscripciones. Cierra primero las que no tienen fecha de fin.']);
            }
            $personal->update($request->safe()->only(['id_tipo_personal', 'fecha_ingreso', 'fecha_termino', 'motivo_periodo']));
            Promociones::recordPeriod($personal, $beforeStart, $beforeEnd, $personal->id_promocion, $request->user()->id);
            $academic = DB::table('datos_academicos')->where('id_personal', $id_personal)->lockForUpdate()->first();
            if ($academic?->deleted_at) {
                throw ValidationException::withMessages(['matricula' => 'Existe un registro académico archivado. Requiere revisión antes de reemplazarlo.']);
            }
            $values = [...$request->safe()->only(['id_institucion', 'id_carrera', 'id_nivel_academico', 'matricula', 'promedio']), 'updated_at' => now()];
            if ($academic) {
                DB::table('datos_academicos')->where('id_dat_academico', $academic->id_dat_academico)->update($values);
            } else {
                DB::table('datos_academicos')->insert([...$values, 'id_personal' => $id_personal, 'created_at' => now()]);
            }
        }, 3);

        return to_route('personal.show', $id_personal)->with('status', 'Datos académicos y participación actualizados.');
    }

    private function academicos(int $id_personal): ?object
    {
        return DB::table('datos_academicos as d')->where('d.id_personal', $id_personal)->whereNull('d.deleted_at')
            ->leftJoin('instituciones as i', 'i.id_institucion', '=', 'd.id_institucion')
            ->leftJoin('carreras as c', 'c.id_carrera', '=', 'd.id_carrera')
            ->leftJoin('nivel_academico as n', 'n.id_nivel_academico', '=', 'd.id_nivel_academico')
            ->select('d.matricula', 'd.promedio', 'i.nombre as institucion', 'i.clave_institucion', 'c.nombre as carrera', 'n.nombre as nivel_academico')->first();
    }

    /** @return Collection<int, object> */
    private function adscripciones(int $id_personal): Collection
    {
        $query = DB::table('adscripciones_personal as a')->where('a.id_personal', $id_personal)->whereNull('a.deleted_at')
            ->leftJoin('unidades_medicas as u', 'u.id_unidad', '=', 'a.id_unidad')
            ->leftJoin('localidades as l', 'l.id_localidad', '=', 'u.id_localidad')
            ->leftJoin('municipios as m', 'm.id_municipio', '=', 'l.id_municipio')
            ->leftJoin('entidades_federativas as e', 'e.id_entidad_federativa', '=', 'm.id_entidad_federativa')
            ->leftJoin('instituciones_salud as s', 's.id_institucion_salud', '=', 'u.id_institucion_salud')
            ->leftJoin('jurisdicciones as j', 'j.id_jurisdiccion', '=', 'u.id_jurisdiccion')
            ->leftJoin('tipo_unidades as t', 't.id_tipo_unidad', '=', 'u.id_tipo_unidad')
            ->leftJoin('tipologias_unidades as tu', 'tu.id_tipologia', '=', 'u.id_tipologia')
            ->select('a.*', 'u.nom_unidad as unidad', 'u.clave_clues', 'l.nom_loc as localidad', 'm.nom_mun as municipio', 'e.nombre as entidad', 's.nombre as institucion_salud', 'j.nombre as jurisdiccion', 't.nombre as tipo_unidad', 'tu.nombre as tipologia');
        foreach (['programas' => 'id_programa', 'vertientes' => 'id_vertiente', 'tipos_campos_clinicos' => 'id_tipo_campo_clinico', 'fuentes_financiamiento_beca' => 'id_fuente_financiamiento_beca'] as $table => $key) {
            $query->leftJoin($table, $table.'.'.$key, '=', 'a.'.$key)->addSelect($table.'.nombre as '.$table);
        }

        return $query->addSelect('programas.clave_programa')->orderByDesc('a.fecha_inicio')->orderByDesc('a.id_adscripcion')->get();
    }
}
