<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador') && $this->user()->can('acceso.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
            'rol' => ['required', Rule::in(['administrador', 'asesor', 'pasante'])],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Este campo es obligatorio.', 'email' => 'Escribe un correo válido.', 'unique' => 'Este correo ya tiene una cuenta.', 'min' => 'La contraseña debe tener al menos :min caracteres.', 'max' => 'No debe superar :max caracteres.', 'confirmed' => 'Las contraseñas no coinciden.', 'in' => 'Selecciona un rol válido.'];
    }
}
