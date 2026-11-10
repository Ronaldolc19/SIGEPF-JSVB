<?php

namespace App\Http\Controllers;

use App\Catalogo;
use App\Http\Requests\SaveCatalogoRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function index(): Response
    {
        $catalogos = [];
        foreach (config('catalogos') as $key => $definition) {
            $catalogos[] = ['slug' => $key, 'title' => $definition['title'], 'group' => $definition['group'], 'count' => Catalogo::query($key)->count()];
        }

        return Inertia::render('catalogos', ['catalogos' => $catalogos]);
    }

    public function show(Request $request, string $catalogo): Response
    {
        $definition = Catalogo::definition($catalogo);
        $rules = ['buscar' => ['nullable', 'string', 'max:100']];
        foreach ($definition['fields'] as $column => $field) {
            if (isset($field['reference'])) {
                $rules[$column] = ['nullable', 'integer', 'min:1'];
            }
        }
        $filters = $request->validate($rules);
        $query = Catalogo::query($catalogo)->select($catalogo.'.*');
        foreach ($definition['fields'] as $column => $field) {
            if (isset($field['reference'])) {
                $parent = Catalogo::definition($field['reference']);
                $alias = 'ref_'.$column;
                $query->leftJoin($field['reference'].' as '.$alias, $alias.'.'.$parent['key'], '=', $catalogo.'.'.$column)
                    ->addSelect($alias.'.'.$parent['label'].' as '.$column.'_label');
                if (! empty($filters[$column])) {
                    $query->where($catalogo.'.'.$column, $filters[$column]);
                }
            }
        }
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($definition, $catalogo, $search): void {
                foreach ($definition['fields'] as $column => $field) {
                    if (! isset($field['reference'])) {
                        $searchQuery->orWhere($catalogo.'.'.$column, 'like', '%'.$search.'%');
                    }
                }
            });
        }

        return Inertia::render('catalogo-list', [
            'catalogo' => $catalogo, 'definition' => $definition,
            'records' => $query->orderBy($catalogo.'.'.$definition['label'])->orderBy($catalogo.'.'.$definition['key'])->paginate(15)->withQueryString(),
            'filters' => $filters,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(string $catalogo): Response
    {
        return Inertia::render('catalogo-form', ['catalogo' => $catalogo, 'definition' => Catalogo::definition($catalogo), 'record' => null]);
    }

    public function edit(string $catalogo, int $registro): Response
    {
        return Inertia::render('catalogo-form', ['catalogo' => $catalogo, 'definition' => Catalogo::definition($catalogo), 'record' => Catalogo::find($catalogo, $registro)]);
    }

    public function options(Request $request, string $catalogo): JsonResponse
    {
        $definition = Catalogo::definition($catalogo);
        $data = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'selected' => ['nullable', 'integer', 'min:1'], 'id_municipio' => ['nullable', 'integer', 'min:1']]);
        $query = Catalogo::optionsQuery($catalogo);
        if ($catalogo === 'localidades' && ! empty($data['id_municipio'])) {
            $query->where('localidades.id_municipio', $data['id_municipio']);
        }
        $selectedQuery = clone $query;
        if (! empty($data['buscar'])) {
            $search = $data['buscar'];
            $query->where(function (Builder $names) use ($catalogo, $definition, $search): void {
                $names->where($catalogo.'.'.$definition['label'], 'like', '%'.$search.'%');
                if ($catalogo === 'localidades') {
                    $names->orWhere('municipios.nom_mun', 'like', '%'.$search.'%');
                }
                if ($catalogo === 'municipios') {
                    $names->orWhere('entidades_federativas.nombre', 'like', '%'.$search.'%');
                }
                if ($catalogo === 'unidades_medicas') {
                    $names->orWhere('unidades_medicas.clave_clues', 'like', '%'.$search.'%');
                }
                if ($catalogo === 'programas') {
                    $names->orWhere('programas.clave_programa', 'like', '%'.$search.'%');
                }
                if ($catalogo === 'instituciones') {
                    $names->orWhere('instituciones.clave_institucion', 'like', '%'.$search.'%');
                }
            });
        }
        $options = $query->limit(30)->get();
        if (! empty($data['selected']) && ! $options->contains('id', (int) $data['selected'])) {
            $selected = $selectedQuery->where($catalogo.'.'.$definition['key'], $data['selected'])->first();
            if ($selected) {
                $options->prepend($selected);
            }
        }

        return response()->json(['options' => $options]);
    }

    public function store(SaveCatalogoRequest $request, string $catalogo): RedirectResponse|JsonResponse
    {
        $id = $this->save($request, $catalogo);
        if ($request->expectsJson()) {
            $definition = Catalogo::definition($catalogo);

            return response()->json(['option' => Catalogo::optionsQuery($catalogo)->where($catalogo.'.'.$definition['key'], $id)->first()], 201);
        }

        return to_route('catalogos.show', $catalogo)->with('status', 'Registro creado correctamente.');
    }

    public function update(SaveCatalogoRequest $request, string $catalogo, int $registro): RedirectResponse
    {
        $this->save($request, $catalogo, $registro);

        return to_route('catalogos.show', $catalogo)->with('status', 'Registro actualizado correctamente.');
    }

    private function save(SaveCatalogoRequest $request, string $catalogo, ?int $registro = null): int
    {
        $definition = Catalogo::definition($catalogo);
        try {
            return DB::transaction(function () use ($request, $catalogo, $registro, $definition): int {
                $values = $request->validated();
                foreach ($definition['fields'] as $column => $field) {
                    if (isset($field['reference']) && ! empty($values[$column])) {
                        $parent = Catalogo::definition($field['reference']);
                        $exists = Catalogo::query($field['reference'])->where($parent['key'], $values[$column])->lockForUpdate()->first();
                        if (! $exists) {
                            throw ValidationException::withMessages([$column => 'La opción seleccionada ya no está disponible.']);
                        }
                    }
                }
                $values['updated_at'] = now();
                if ($registro !== null) {
                    Catalogo::find($catalogo, $registro, true);
                    Catalogo::query($catalogo)->where($definition['key'], $registro)->update($values);

                    return $registro;
                } else {
                    $values['created_at'] = now();

                    return DB::table($catalogo)->insertGetId($values, $definition['key']);
                }
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            foreach ($definition['fields'] as $column => $field) {
                if ($field['unique'] ?? false) {
                    throw ValidationException::withMessages([$column => 'Ese valor ya fue registrado. Revisa los datos.']);
                }
            }
            throw $exception;
        }
    }

    public function destroy(string $catalogo, int $registro): RedirectResponse
    {
        $definition = Catalogo::definition($catalogo);
        DB::transaction(function () use ($catalogo, $registro, $definition): void {
            Catalogo::find($catalogo, $registro, true);
            foreach ($definition['dependents'] as [$table, $column]) {
                if (DB::table($table)->where($column, $registro)->exists()) {
                    throw ValidationException::withMessages(['registro' => 'No se puede eliminar: este registro tiene datos relacionados, incluidos registros históricos.']);
                }
            }
            Catalogo::query($catalogo)->where($definition['key'], $registro)->update(['deleted_at' => now(), 'updated_at' => now()]);
        }, 3);

        return to_route('catalogos.show', $catalogo)->with('status', 'Registro eliminado. Se conserva en el historial de la base de datos.');
    }
}
