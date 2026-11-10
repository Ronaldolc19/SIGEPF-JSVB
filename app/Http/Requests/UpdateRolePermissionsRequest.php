<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador') && $this->user()->can('acceso.editar')
            && in_array($this->route('rol')->codigo, ['asesor', 'pasante'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $codes = array_keys(config('access.role_modules.'.$this->route('rol')->codigo, []));

        return [
            'modulos' => ['present', 'array', 'list'],
            'modulos.*' => ['required', 'string', 'distinct', Rule::in($codes)],
            'permisos' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'modulos.present' => 'Indica los módulos del rol.',
            'modulos.array' => 'Los módulos deben enviarse como una lista.',
            'modulos.list' => 'Los módulos deben enviarse como una lista.',
            'modulos.*.in' => 'Ese módulo no está disponible para este rol.',
            'modulos.*.distinct' => 'No repitas el mismo módulo.',
            'modulos.*.string' => 'Selecciona un módulo válido.',
            'modulos.*.required' => 'Selecciona un módulo válido.',
            'permisos.prohibited' => 'Selecciona módulos completos; las acciones se definen por rol.',
        ];
    }
}
