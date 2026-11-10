<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador') && $this->user()->can('acceso.editar');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array', 'max:3'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('access.roles'))), Rule::exists('roles', 'codigo')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'roles.present' => 'Indica los roles de la cuenta.',
            'roles.array' => 'Los roles deben enviarse como una lista.',
            'roles.max' => 'Solo existen tres roles disponibles.',
            'roles.*.in' => 'Selecciona un rol válido.',
            'roles.*.exists' => 'El rol seleccionado no está disponible.',
            'roles.*.distinct' => 'No repitas el mismo rol.',
        ];
    }
}
