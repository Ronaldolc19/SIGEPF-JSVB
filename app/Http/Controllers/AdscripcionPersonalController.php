<?php

namespace App\Http\Controllers;

use App\Catalogo;
use App\Http\Requests\SaveAdscripcionPersonalRequest;
use App\Models\PersonalFormacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdscripcionPersonalController extends Controller
{
    public function create(int $id_personal): Response
    {
        return $this->form($id_personal);
    }

    public function edit(int $id_personal, int $id_adscripcion): Response
    {
        return $this->form($id_personal, $id_adscripcion);
    }

    private function form(int $id_personal, ?int $id_adscripcion = null): Response
    {
        $personal = PersonalFormacion::query()->whereHas('persona')->with('persona:id_persona,nombre,ap,am')->findOrFail($id_personal);
        $record = $id_adscripcion === null ? null : DB::table('adscripciones_personal')->where('id_personal', $id_personal)
            ->where('id_adscripcion', $id_adscripcion)->whereNull('deleted_at')->first();
        abort_if($id_adscripcion !== null && ! $record, 404);

        return Inertia::render('adscripcion-form', ['personal' => $personal, 'record' => $record]);
    }

    public function store(SaveAdscripcionPersonalRequest $request, int $id_personal): RedirectResponse
    {
        return $this->save($request, $id_personal);
    }

    public function update(SaveAdscripcionPersonalRequest $request, int $id_personal, int $id_adscripcion): RedirectResponse
    {
        return $this->save($request, $id_personal, $id_adscripcion);
    }

    private function save(SaveAdscripcionPersonalRequest $request, int $id_personal, ?int $id_adscripcion = null): RedirectResponse
    {
        DB::transaction(function () use ($request, $id_personal, $id_adscripcion): void {
            $personal = PersonalFormacion::query()->whereHas('persona')->lockForUpdate()->findOrFail($id_personal);
            if ($id_adscripcion !== null) {
                abort_unless(DB::table('adscripciones_personal')->where('id_personal', $id_personal)->where('id_adscripcion', $id_adscripcion)->whereNull('deleted_at')->lockForUpdate()->first(), 404);
            }
            $values = $request->validated();
            foreach (['id_unidad' => 'unidades_medicas', 'id_programa' => 'programas', 'id_vertiente' => 'vertientes', 'id_tipo_campo_clinico' => 'tipos_campos_clinicos', 'id_fuente_financiamiento_beca' => 'fuentes_financiamiento_beca'] as $key => $table) {
                if (! empty($values[$key]) && ! Catalogo::query($table)->where($key, $values[$key])->lockForUpdate()->first()) {
                    throw ValidationException::withMessages([$key => 'Selecciona una opción vigente.']);
                }
            }
            $start = $values['fecha_inicio'];
            $end = $values['fecha_fin'] ?? null;
            if (! $personal->fecha_ingreso || $start < $personal->fecha_ingreso->format('Y-m-d')
                || ($personal->fecha_termino && (! $end || $end > $personal->fecha_termino->format('Y-m-d')))) {
                throw ValidationException::withMessages(['fecha_inicio' => 'Completa las fechas de la participación y registra una adscripción dentro de ese periodo.']);
            }
            $overlap = DB::table('adscripciones_personal')->where('id_personal', $id_personal)->whereNull('deleted_at')
                ->when($id_adscripcion !== null, fn (Builder $query): Builder => $query->where('id_adscripcion', '!=', $id_adscripcion))
                ->when($end !== null, fn (Builder $query): Builder => $query->where('fecha_inicio', '<=', $end))
                ->where(fn (Builder $query): Builder => $query->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $start))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['fecha_inicio' => 'El periodo se superpone con otra adscripción. Cierra la anterior antes de registrar el cambio de unidad.']);
            }
            $values['updated_at'] = now();
            if ($id_adscripcion === null) {
                DB::table('adscripciones_personal')->insert([...$values, 'id_personal' => $id_personal, 'created_at' => now()]);
            } else {
                DB::table('adscripciones_personal')->where('id_adscripcion', $id_adscripcion)->update($values);
            }
        }, 3);

        return to_route('personal.show', $id_personal)->with('status', 'Adscripción guardada. Se conserva el historial de unidades.');
    }
}
