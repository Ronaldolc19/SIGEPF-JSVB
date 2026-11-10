<?php

namespace App\Http\Controllers;

use App\Catalogo;
use App\Http\Requests\SavePersonaRequest;
use App\Models\Asesor;
use App\Models\Persona;
use App\Models\PersonalFormacion;
use App\Models\User;
use App\Promociones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonaController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['buscar'] ?? '');
        $query = Persona::query()->select(['id_persona', 'nombre', 'ap', 'am', 'correo', 'telefono']);
        foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $query->where(function (Builder $query) use ($word): void {
                foreach (['nombre', 'ap', 'am', 'correo', 'curp'] as $field) {
                    $query->orWhere($field, 'like', '%'.$word.'%');
                }
            });
        }

        return Inertia::render('personas', ['records' => $query->orderBy('ap')->orderBy('nombre')->orderBy('id_persona')->paginate(15)->withQueryString(), 'buscar' => $search]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $data = $request->validate(['usuario' => ['nullable', 'integer', 'exists:users,id']]);
        $account = ! empty($data['usuario']) ? User::findOrFail($data['usuario']) : null;
        if ($account) {
            abort_unless($request->user()->can('acceso.editar'), 403);
            if ($account->id_persona !== null) {
                return to_route('usuarios.persona.edit', $account)->with('status', 'Esta cuenta ya tiene una persona vinculada.');
            }
        }

        return Inertia::render('persona-form', ['promociones' => Promociones::options(), 'persona' => null, 'account' => $account ? [...$account->only(['id', 'name', 'email']), 'perfil' => $account->hasRole('asesor') ? 'asesor' : ($account->hasRole('pasante') ? 'personal_formacion' : '')] : null]);
    }

    public function edit(Persona $persona): Response
    {
        return Inertia::render('persona-form', ['promociones' => Promociones::options(), 'persona' => $persona, 'perfiles' => $this->perfiles($persona), 'municipio' => DB::table('localidades')->where('id_localidad', $persona->id_localidad)->value('id_municipio')]);
    }

    public function show(Request $request, Persona $persona): Response
    {
        $labels = [];
        foreach (['id_localidad' => 'localidades', 'id_genero' => 'generos', 'id_nacionalidad' => 'nacionalidades', 'id_entidad_federativa' => 'entidades_federativas'] as $field => $table) {
            $labels[$field] = $persona->{$field} ? Catalogo::optionsQuery($table)->where($table.'.'.Catalogo::definition($table)['key'], $persona->{$field})->first() : null;
        }

        $residencia = DB::table('localidades as l')->where('l.id_localidad', $persona->id_localidad)
            ->leftJoin('municipios as m', 'm.id_municipio', '=', 'l.id_municipio')
            ->leftJoin('entidades_federativas as e', 'e.id_entidad_federativa', '=', 'm.id_entidad_federativa')
            ->select('m.nom_mun as municipio', 'e.nombre as entidad')->first();

        return Inertia::render('persona-show', [
            'persona' => $persona, 'perfiles' => $this->perfiles($persona), 'labels' => $labels,
            'usuario' => $persona->usuario()->first(['id', 'email']), 'status' => $request->session()->get('status'),
            'participaciones' => $persona->participaciones()->orderByDesc('id_personal')->get(['id_personal', 'estado']),
            'edad' => $persona->fecha_nacimiento?->age, 'residencia' => $residencia,
        ]);
    }

    public function store(SavePersonaRequest $request): RedirectResponse
    {
        return to_route('personas.show', $this->save($request))->with('status', 'Persona registrada correctamente.');
    }

    public function update(SavePersonaRequest $request, Persona $persona): RedirectResponse
    {
        return to_route('personas.show', $this->save($request, $persona))->with('status', 'Datos actualizados correctamente.');
    }

    private function save(SavePersonaRequest $request, ?Persona $persona = null): Persona
    {
        try {
            return DB::transaction(function () use ($request, $persona): Persona {
                $data = $request->validated();
                $account = ! empty($data['id_usuario']) ? User::query()->lockForUpdate()->findOrFail($data['id_usuario']) : null;
                if ($account && $account->id_persona !== null) {
                    throw ValidationException::withMessages(['id_usuario' => 'Esta cuenta ya tiene una persona vinculada.']);
                }
                unset($data['id_usuario']);
                $municipio = $data['id_municipio'] ?? null;
                $perfil = $data['perfil'] ?? null;
                $participacion = array_intersect_key($data, array_flip(['id_tipo_personal', 'fecha_ingreso', 'fecha_termino']));
                unset($data['id_municipio'], $data['perfil'], $data['id_tipo_personal'], $data['fecha_ingreso'], $data['fecha_termino']);
                foreach (['id_localidad' => 'localidades', 'id_genero' => 'generos', 'id_nacionalidad' => 'nacionalidades', 'id_entidad_federativa' => 'entidades_federativas'] as $field => $table) {
                    if (! empty($data[$field]) && ! Catalogo::query($table)->where(Catalogo::definition($table)['key'], $data[$field])->when($field === 'id_localidad', fn (\Illuminate\Database\Query\Builder $query): \Illuminate\Database\Query\Builder => $query->where('id_municipio', $municipio))->lockForUpdate()->first()) {
                        throw ValidationException::withMessages([$field => 'Selecciona un registro vigente del catálogo.']);
                    }
                }
                $record = $persona ? Persona::query()->lockForUpdate()->findOrFail($persona->getKey()) : new Persona;
                $record->fill($data)->save();
                if ($perfil) {
                    $existingAdvisor = $record->asesor()->withTrashed()->first();
                    $existingParticipation = $record->participaciones()->withTrashed()->exists();
                    if ($existingAdvisor || $existingParticipation) {
                        $sameProfile = ($perfil === 'asesor' && $existingAdvisor && ! $existingAdvisor->trashed())
                            || ($perfil === 'personal_formacion' && $record->participaciones()->exists());
                        if (! $sameProfile) {
                            throw ValidationException::withMessages(['perfil' => 'Esta persona ya tiene un perfil o historial registrado. No se puede sustituir desde este formulario.']);
                        }
                    } elseif ($perfil === 'asesor') {
                        Asesor::create(['id_persona' => $record->getKey()]);
                    } else {
                        if (! Catalogo::query('tipos_personal')->where('id_tipo_personal', $participacion['id_tipo_personal'])->lockForUpdate()->first()) {
                            throw ValidationException::withMessages(['id_tipo_personal' => 'Selecciona un tipo de personal vigente.']);
                        }
                        PersonalFormacion::create([...$participacion, 'id_persona' => $record->getKey(), 'estado' => 'En formacion']);
                    }
                }

                if ($account) {
                    $account->id_persona = $record->getKey();
                    $account->save();
                }

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['curp' => 'Esta CURP ya está registrada.']);
        }
    }

    /** @return list<string> */
    private function perfiles(Persona $persona): array
    {
        $perfiles = [];
        if ($persona->asesor()->withTrashed()->exists()) {
            $perfiles[] = 'Asesor';
        }
        if ($persona->participaciones()->withTrashed()->exists()) {
            $perfiles[] = 'Personal en formación';
        }

        return $perfiles;
    }
}
