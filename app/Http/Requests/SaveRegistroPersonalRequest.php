<?php

namespace App\Http\Requests;

use App\Models\PersonalFormacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRegistroPersonalRequest extends FormRequest
{
    public const PERSONA_FIELDS = ['nombre', 'ap', 'am', 'fecha_nacimiento', 'curp', 'rfc', 'correo', 'telefono', 'desc_domicilio', 'id_localidad', 'id_genero', 'id_nacionalidad', 'id_entidad_federativa'];

    public const ACADEMIC_FIELDS = ['id_institucion', 'id_carrera', 'id_nivel_academico', 'matricula', 'promedio'];

    public const REFERENCES = [
        'id_genero' => 'generos', 'id_nacionalidad' => 'nacionalidades', 'id_entidad_federativa' => 'entidades_federativas',
        'id_municipio' => 'municipios', 'id_localidad' => 'localidades', 'id_tipo_personal' => 'tipos_personal',
        'id_institucion' => 'instituciones', 'id_carrera' => 'carreras', 'id_nivel_academico' => 'nivel_academico',
        'id_unidad' => 'unidades_medicas', 'id_programa' => 'programas', 'id_vertiente' => 'vertientes',
        'id_tipo_campo_clinico' => 'tipos_campos_clinicos', 'id_fuente_financiamiento_beca' => 'fuentes_financiamiento_beca',
    ];

    public const ADSCRIPTION_FIELDS = ['id_unidad', 'id_programa', 'id_vertiente', 'id_tipo_campo_clinico', 'id_fuente_financiamiento_beca', 'numero_campo_clinico'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador')
            && $this->user()->can($this->isMethod('POST') ? 'personal.crear' : 'personal.editar')
            && (! $this->filled('id_usuario') || $this->user()->can('acceso.editar'));
    }

    protected function prepareForValidation(): void
    {
        foreach (['curp', 'rfc'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => mb_strtoupper(trim($this->input($key)))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $personal = $this->route('id_personal') ? PersonalFormacion::findOrFail($this->route('id_personal')) : null;
        $personaId = $personal?->id_persona ?? $this->integer('id_persona');
        $rules = [
            'id_promocion' => [$personal ? 'nullable' : 'required', 'integer', Rule::exists('promociones', 'id_promocion')->whereNull('deleted_at')],
            'motivo_periodo' => ['nullable', 'string', 'max:2000'],
            'id_persona' => [$personal ? 'prohibited' : 'nullable', 'integer', Rule::exists('personas', 'id_persona')->whereNull('deleted_at')],
            'id_usuario' => [$personal ? 'prohibited' : 'nullable', 'integer', Rule::exists('users', 'id')],
            'id_adscripcion' => ['nullable', 'integer', 'min:1'],
            'nombre' => ['required', 'string', 'max:255'], 'ap' => ['required', 'string', 'max:255'], 'am' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'before_or_equal:fecha_ingreso'],
            'edad_constancia' => ['required', 'integer', 'between:15,100'],
            'curp' => ['required', 'string', 'size:18', Rule::unique('personas', 'curp')->ignore($personaId, 'id_persona')],
            'rfc' => ['nullable', 'string', 'max:13'],
            'correo' => ['nullable', 'email', 'max:255'], 'telefono' => ['nullable', 'string', 'max:20'],
            'desc_domicilio' => ['nullable', 'string', 'max:255'],
            'fecha_ingreso' => ['required', 'date_format:Y-m-d'],
            'fecha_termino' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_ingreso'],
            'fecha_inicio_adscripcion' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_ingreso', 'before_or_equal:fecha_termino'],
            'fecha_fin_adscripcion' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio_adscripcion', 'before_or_equal:fecha_termino'],
            'numero_campo_clinico' => ['required', 'string', 'max:100'],
            'matricula' => ['nullable', 'string', 'max:100'], 'promedio' => ['nullable', 'numeric', 'between:0,100'],
        ];
        foreach (self::REFERENCES as $key => $table) {
            $exists = Rule::exists($table, $key)->whereNull('deleted_at');
            if ($key === 'id_localidad') {
                $exists->where('id_municipio', $this->integer('id_municipio'));
            }
            $rules[$key] = [in_array($key, ['id_municipio', 'id_localidad', 'id_nivel_academico'], true) ? 'nullable' : 'required', 'integer', $exists];
        }
        $rules['id_municipio'][] = 'required_with:id_localidad';

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Completa este dato de la constancia.', 'exists' => 'Selecciona una opción vigente del catálogo.',
            'curp.unique' => 'Esta CURP ya está registrada. Abre la persona existente para continuar su registro.',
            'curp.size' => 'La CURP debe tener 18 caracteres.', 'email' => 'Escribe un correo válido.',
            'date_format' => 'Escribe una fecha válida.', 'after_or_equal' => 'La fecha debe ser igual o posterior al inicio.',
            'before_or_equal' => 'La fecha queda fuera del periodo permitido.', 'integer' => 'Selecciona una opción válida.',
            'max' => 'No debe superar :max caracteres.', 'between' => 'El valor debe estar entre :min y :max.',
            'required_with' => 'Selecciona el municipio de la localidad.', 'prohibited' => 'Este dato no se puede cambiar desde aquí.',
        ];
    }
}
