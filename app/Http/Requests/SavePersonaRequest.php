<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador') && $this->user()->can($this->isMethod('POST') ? 'personal.crear' : 'personal.editar')
            && (! $this->filled('id_usuario') || $this->user()->can('acceso.editar'));
    }

    protected function prepareForValidation(): void
    {
        foreach (['curp', 'rfc'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => mb_strtoupper(trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'id_usuario' => [Rule::excludeIf(! $this->isMethod('POST')), 'nullable', 'integer', Rule::exists('users', 'id')],
            'perfil' => ['nullable', Rule::in(['asesor', 'personal_formacion'])],
            'id_tipo_personal' => ['exclude_unless:perfil,personal_formacion', 'required', 'integer', Rule::exists('tipos_personal', 'id_tipo_personal')->whereNull('deleted_at')],
            'fecha_ingreso' => ['exclude_unless:perfil,personal_formacion', 'nullable', 'required_with:fecha_termino', 'date_format:Y-m-d'],
            'fecha_termino' => ['exclude_unless:perfil,personal_formacion', 'nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_ingreso'],
            'nombre' => ['required', 'string', 'max:255'],
            'ap' => ['required', 'string', 'max:255'],
            'am' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'curp' => ['nullable', 'string', 'size:18', Rule::unique('personas', 'curp')->ignore($this->route('persona'))],
            'rfc' => ['nullable', 'string', 'max:13'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'desc_domicilio' => ['nullable', 'string', 'max:255'],
            'id_municipio' => ['nullable', 'required_with:id_localidad', 'integer', Rule::exists('municipios', 'id_municipio')->whereNull('deleted_at')],
            'id_localidad' => ['nullable', 'integer', Rule::exists('localidades', 'id_localidad')->whereNull('deleted_at')->where('id_municipio', $this->integer('id_municipio'))],
            'id_genero' => ['nullable', 'integer', Rule::exists('generos', 'id_genero')->whereNull('deleted_at')],
            'id_nacionalidad' => ['nullable', 'integer', Rule::exists('nacionalidades', 'id_nacionalidad')->whereNull('deleted_at')],
            'id_entidad_federativa' => ['nullable', 'integer', Rule::exists('entidades_federativas', 'id_entidad_federativa')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Este campo es obligatorio.', 'id_municipio.required_with' => 'Selecciona el municipio de la localidad.', 'max' => 'No debe superar :max caracteres.', 'size' => 'Debe contener :size caracteres.', 'unique' => 'Esta CURP ya está registrada.', 'exists' => 'Selecciona un registro vigente del catálogo.', 'email' => 'Escribe un correo válido.', 'date_format' => 'Escribe una fecha válida.', 'before_or_equal' => 'La fecha no puede ser futura.', 'integer' => 'Selecciona una opción válida.', 'string' => 'Escribe un texto válido.'];
    }
}
