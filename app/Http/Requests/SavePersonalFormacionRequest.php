<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePersonalFormacionRequest extends FormRequest
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
            'motivo_periodo' => ['nullable', 'string', 'max:2000'],
            'id_tipo_personal' => ['required', 'integer', Rule::exists('tipos_personal', 'id_tipo_personal')->whereNull('deleted_at')],
            'fecha_ingreso' => ['required', 'date_format:Y-m-d'],
            'fecha_termino' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_ingreso'],
            'id_institucion' => ['required', 'integer', Rule::exists('instituciones', 'id_institucion')->whereNull('deleted_at')],
            'id_carrera' => ['required', 'integer', Rule::exists('carreras', 'id_carrera')->whereNull('deleted_at')],
            'id_nivel_academico' => ['required', 'integer', Rule::exists('nivel_academico', 'id_nivel_academico')->whereNull('deleted_at')],
            'matricula' => ['required', 'string', 'max:100'],
            'promedio' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'required_with' => 'Indica el motivo al registrar una fecha de fin.',
            'prohibited_without' => 'El motivo de cierre requiere una fecha de fin.',
            'exists' => 'Selecciona una opción vigente del catálogo.',
            'date_format' => 'Escribe una fecha válida.',
            'after_or_equal' => 'La fecha de fin debe ser igual o posterior al inicio.',
            'between' => 'El promedio debe estar entre 0 y 100, según la escala de tu institución.',
            'max' => 'No debe superar :max caracteres.',
        ];
    }
}
