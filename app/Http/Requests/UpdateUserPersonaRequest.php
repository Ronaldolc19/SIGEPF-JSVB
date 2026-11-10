<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador') && $this->user()->can('acceso.editar');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['id_persona' => ['present', 'nullable', 'integer',
            Rule::exists('personas', 'id_persona')->whereNull('deleted_at'),
            Rule::unique('users', 'id_persona')->ignore($this->route('user')->id),
        ]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id_persona.present' => 'Selecciona una persona o indica que deseas retirar el vínculo.',
            'id_persona.integer' => 'Selecciona una persona válida.',
            'id_persona.exists' => 'La persona no existe o está eliminada.',
            'id_persona.unique' => 'Esta persona ya está vinculada a otra cuenta.',
        ];
    }
}
