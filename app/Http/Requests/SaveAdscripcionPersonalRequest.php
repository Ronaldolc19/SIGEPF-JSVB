<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdscripcionPersonalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador') && $this->user()->can('personal.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_unidad' => ['required', 'integer', Rule::exists('unidades_medicas', 'id_unidad')->whereNull('deleted_at')],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'motivo_fin' => ['nullable', 'required_with:fecha_fin', Rule::prohibitedIf(! $this->filled('fecha_fin')), 'string', 'max:2000'],
            'numero_campo_clinico' => ['nullable', 'string', 'max:100'],
            'id_programa' => ['nullable', 'integer', Rule::exists('programas', 'id_programa')->whereNull('deleted_at')],
            'id_vertiente' => ['nullable', 'integer', Rule::exists('vertientes', 'id_vertiente')->whereNull('deleted_at')],
            'id_tipo_campo_clinico' => ['nullable', 'integer', Rule::exists('tipos_campos_clinicos', 'id_tipo_campo_clinico')->whereNull('deleted_at')],
            'id_fuente_financiamiento_beca' => ['nullable', 'integer', Rule::exists('fuentes_financiamiento_beca', 'id_fuente_financiamiento_beca')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'required_with' => 'Indica el motivo al registrar una fecha de fin.',
            'prohibited' => 'El motivo de cierre requiere una fecha de fin.',
            'exists' => 'Selecciona una opción vigente del catálogo.',
            'date_format' => 'Escribe una fecha válida.',
            'after_or_equal' => 'La fecha de fin debe ser igual o posterior al inicio.',
            'between' => 'El promedio debe estar entre 0 y 100, según la escala de tu institución.',
            'max' => 'No debe superar :max caracteres.',
        ];
    }
}
