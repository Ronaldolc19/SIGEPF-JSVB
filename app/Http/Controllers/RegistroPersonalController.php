<?php

namespace App\Http\Controllers;

use App\Catalogo;
use App\Http\Requests\SaveRegistroPersonalRequest;
use App\Models\Persona;
use App\Models\PersonalFormacion;
use App\Models\User;
use App\Promociones;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistroPersonalController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'persona' => ['nullable', 'integer', Rule::exists('personas', 'id_persona')->whereNull('deleted_at')],
            'usuario' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'promocion' => ['nullable', 'integer', Rule::exists('promociones', 'id_promocion')->whereNull('deleted_at')->where('permite_inscripciones', true)->where('estado', '!=', 'Cerrada')],
        ]);
        $account = ! empty($data['usuario']) ? User::findOrFail($data['usuario']) : null;
        if ($account) {
            abort_unless($request->user()->can('acceso.editar'), 403);
        }
        $personId = $data['persona'] ?? $account?->id_persona;
        $persona = $personId ? Persona::findOrFail($personId) : null;
        if ($persona && $existing = $persona->participaciones()->latest('id_personal')->first()) {
            return to_route('personal.registro.edit', $existing->id_personal);
        }

        return Inertia::render('registro-personal', [
            'promociones' => Promociones::options(),
            'initial' => $persona ? [...$persona->only(SaveRegistroPersonalRequest::PERSONA_FIELDS), 'id_persona' => $persona->id_persona,
                'id_municipio' => DB::table('localidades')->where('id_localidad', $persona->id_localidad)->value('id_municipio')] : [],
            'account' => $account?->only(['id', 'name', 'email']),
            'personalId' => null,
            'selectedPromotion' => $data['promocion'] ?? null,
        ]);
    }

    public function edit(int $id_personal): Response
    {
        $personal = PersonalFormacion::query()->whereHas('persona')->with('persona')->findOrFail($id_personal);
        $academic = DB::table('datos_academicos')->where('id_personal', $id_personal)->whereNull('deleted_at')->first();
        $adscripcion = DB::table('adscripciones_personal')->where('id_personal', $id_personal)->whereNull('deleted_at')->orderByDesc('fecha_inicio')->orderByDesc('id_adscripcion')->first();

        return Inertia::render('registro-personal', [
            'promociones' => Promociones::options($personal->id_promocion, ! $personal->id_promocion),
            'personalId' => $id_personal, 'account' => null,
            'initial' => [
                ...$personal->persona->only(SaveRegistroPersonalRequest::PERSONA_FIELDS),
                ...$personal->only(['id_tipo_personal', 'fecha_ingreso', 'fecha_termino', 'edad_constancia', 'id_promocion', 'motivo_periodo']),
                ...array_intersect_key((array) $academic, array_flip(SaveRegistroPersonalRequest::ACADEMIC_FIELDS)),
                ...array_intersect_key((array) $adscripcion, array_flip(SaveRegistroPersonalRequest::ADSCRIPTION_FIELDS)),
                'id_municipio' => DB::table('localidades')->where('id_localidad', $personal->persona->id_localidad)->value('id_municipio'),
                'id_adscripcion' => $adscripcion?->id_adscripcion,
                'fecha_ingreso' => $personal->fecha_ingreso?->format('Y-m-d'),
                'fecha_termino' => $personal->fecha_termino?->format('Y-m-d'),
                'fecha_nacimiento' => $personal->persona->fecha_nacimiento?->format('Y-m-d'),
                'fecha_inicio_adscripcion' => $adscripcion?->fecha_inicio,
                'fecha_fin_adscripcion' => $adscripcion?->fecha_fin,
            ],
        ]);
    }

    public function store(SaveRegistroPersonalRequest $request): RedirectResponse
    {
        return $this->save($request);
    }

    public function update(SaveRegistroPersonalRequest $request, int $id_personal): RedirectResponse
    {
        return $this->save($request, $id_personal);
    }

    private function save(SaveRegistroPersonalRequest $request, ?int $id_personal = null): RedirectResponse
    {
        try {
            $personal = DB::transaction(function () use ($request, $id_personal): PersonalFormacion {
                $data = $request->validated();
                $personal = $id_personal ? PersonalFormacion::query()->whereHas('persona')->lockForUpdate()->findOrFail($id_personal) : new PersonalFormacion;
                $beforeStart = $personal->fecha_ingreso?->format('Y-m-d');
                $beforeEnd = $personal->fecha_termino?->format('Y-m-d');
                $beforePromotion = $personal->id_promocion;
                Promociones::validateParticipation($personal, ! empty($data['id_promocion']) ? (int) $data['id_promocion'] : null, (int) $data['id_carrera'], $data['fecha_ingreso'], $data['fecha_termino'], $data['motivo_periodo'] ?? null);
                $account = ! empty($data['id_usuario']) ? User::query()->lockForUpdate()->findOrFail($data['id_usuario']) : null;
                $personId = $personal->id_persona ?? ($data['id_persona'] ?? null);
                $persona = $personId ? Persona::query()->lockForUpdate()->findOrFail($personId) : new Persona;
                if (! $id_personal && $persona->exists && ($persona->participaciones()->withTrashed()->exists() || $persona->asesor()->withTrashed()->exists())) {
                    throw ValidationException::withMessages(['id_persona' => 'Esta persona ya tiene un perfil. Abre su participación existente para editarla.']);
                }
                if ($account && ($account->id_persona !== null && $account->id_persona !== $persona->id_persona
                    || ($persona->exists && $persona->usuario()->whereKeyNot($account->id)->exists()))) {
                    throw ValidationException::withMessages(['id_usuario' => 'La cuenta o la persona ya tiene otro vínculo. Revisa Usuarios y permisos.']);
                }
                foreach (SaveRegistroPersonalRequest::REFERENCES as $key => $table) {
                    if (! empty($data[$key])) {
                        $reference = Catalogo::query($table)->where($key, $data[$key])->lockForUpdate()->first();
                        if (! $reference || ($key === 'id_localidad' && (int) $reference->id_municipio !== (int) ($data['id_municipio'] ?? 0))) {
                            throw ValidationException::withMessages([$key => 'Esta opción ya no está disponible o no corresponde al municipio.']);
                        }
                    }
                }
                $persona->fill($request->safe()->only(SaveRegistroPersonalRequest::PERSONA_FIELDS))->save();
                $personal->fill($request->safe()->only(['id_tipo_personal', 'fecha_ingreso', 'fecha_termino', 'edad_constancia', 'id_promocion', 'motivo_periodo']));
                $personal->id_persona = $persona->id_persona;
                if (! $personal->exists) {
                    $personal->estado = 'En formacion';
                }
                $personal->save();
                Promociones::recordPeriod($personal, $beforeStart, $beforeEnd, $beforePromotion, $request->user()->id);
                $academic = DB::table('datos_academicos')->where('id_personal', $personal->id_personal)->lockForUpdate()->first();
                if ($academic?->deleted_at) {
                    throw ValidationException::withMessages(['id_institucion' => 'Existe información académica archivada; requiere revisión antes de sustituirla.']);
                }
                $academicValues = [...$request->safe()->only(SaveRegistroPersonalRequest::ACADEMIC_FIELDS), 'updated_at' => now()];
                if ($academic) {
                    DB::table('datos_academicos')->where('id_dat_academico', $academic->id_dat_academico)->update($academicValues);
                } else {
                    DB::table('datos_academicos')->insert([...$academicValues, 'id_personal' => $personal->id_personal, 'created_at' => now()]);
                }
                $latest = DB::table('adscripciones_personal')->where('id_personal', $personal->id_personal)->whereNull('deleted_at')
                    ->orderByDesc('fecha_inicio')->orderByDesc('id_adscripcion')->lockForUpdate()->first();
                if (($latest?->id_adscripcion ?? 0) !== (int) ($data['id_adscripcion'] ?? 0)) {
                    throw ValidationException::withMessages(['id_adscripcion' => 'El historial cambió mientras editabas. Vuelve a abrir el registro.']);
                }
                $history = DB::table('adscripciones_personal')->where('id_personal', $personal->id_personal)->whereNull('deleted_at')
                    ->when($latest, fn (Builder $query): Builder => $query->where('id_adscripcion', '!=', $latest->id_adscripcion));
                if ((clone $history)->where('fecha_inicio', '<=', $data['fecha_fin_adscripcion'])
                    ->where(fn (Builder $query): Builder => $query->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $data['fecha_inicio_adscripcion']))->exists()) {
                    throw ValidationException::withMessages(['fecha_inicio_adscripcion' => 'Las fechas se superponen con una adscripción anterior.']);
                }
                if ($history->where(fn (Builder $query): Builder => $query->where('fecha_inicio', '<', $data['fecha_ingreso'])->orWhere('fecha_fin', '>', $data['fecha_termino']))->exists()) {
                    throw ValidationException::withMessages(['fecha_ingreso' => 'El periodo debe incluir las adscripciones históricas.']);
                }
                if ($latest && (int) $latest->id_unidad !== (int) $data['id_unidad']) {
                    throw ValidationException::withMessages(['id_unidad' => 'Para un cambio de unidad, cierra la adscripción y registra el traslado desde el historial.']);
                }
                $adscriptionValues = [...$request->safe()->only(SaveRegistroPersonalRequest::ADSCRIPTION_FIELDS),
                    'fecha_inicio' => $data['fecha_inicio_adscripcion'], 'fecha_fin' => $data['fecha_fin_adscripcion'], 'updated_at' => now()];
                if ($latest) {
                    DB::table('adscripciones_personal')->where('id_adscripcion', $latest->id_adscripcion)->update($adscriptionValues);
                } else {
                    DB::table('adscripciones_personal')->insert([...$adscriptionValues, 'id_personal' => $personal->id_personal, 'created_at' => now()]);
                }
                if ($account && $account->id_persona === null) {
                    $account->id_persona = $persona->id_persona;
                    $account->save();
                }

                return $personal;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['curp' => 'El registro ya existe. Revisa la CURP y la participación antes de volver a guardar.']);
        }

        return to_route('personal.show', $personal->id_personal)->with('status', 'Registro completo guardado: persona, formación académica y adscripción.');
    }

    public function unidad(int $id_unidad): JsonResponse
    {
        $unit = DB::table('unidades_medicas as u')->where('u.id_unidad', $id_unidad)->whereNull('u.deleted_at')
            ->leftJoin('localidades as l', 'l.id_localidad', '=', 'u.id_localidad')
            ->leftJoin('municipios as m', 'm.id_municipio', '=', 'l.id_municipio')
            ->leftJoin('entidades_federativas as e', 'e.id_entidad_federativa', '=', 'm.id_entidad_federativa')
            ->leftJoin('instituciones_salud as s', 's.id_institucion_salud', '=', 'u.id_institucion_salud')
            ->leftJoin('jurisdicciones as j', 'j.id_jurisdiccion', '=', 'u.id_jurisdiccion')
            ->leftJoin('tipo_unidades as t', 't.id_tipo_unidad', '=', 'u.id_tipo_unidad')
            ->select('u.id_unidad', 'u.nom_unidad', 'u.clave_clues', 'l.nom_loc as localidad', 'm.nom_mun as municipio',
                'e.nombre as entidad', 's.nombre as institucion_salud', 'j.nombre as jurisdiccion', 't.nombre as tipo_unidad')->first();
        abort_unless($unit, 404);

        return response()->json($unit);
    }
}
