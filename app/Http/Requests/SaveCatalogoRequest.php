<?php

namespace App\Http\Requests;

use App\Catalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('administrador') && $this->user()->can($this->isMethod('POST') ? 'catalogos.crear' : 'catalogos.editar');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $catalogo = $this->route('catalogo');
        $definition = Catalogo::definition($catalogo);
        $record = $this->route('registro') ? Catalogo::find($catalogo, (int) $this->route('registro')) : null;
        $rules = [];
        foreach ($definition['fields'] as $column => $field) {
            $rules[$column] = [$field['required'] ? 'required' : 'nullable'];
            if (isset($field['reference'])) {
                $parent = Catalogo::definition($field['reference']);
                $rules[$column][] = 'integer';
                $rules[$column][] = Rule::exists($field['reference'], $parent['key'])->whereNull('deleted_at');
            } else {
                $rules[$column][] = 'string';
                $rules[$column][] = 'max:'.$field['max'];
            }
            if (isset($field['pattern'])) {
                $rules[$column][] = 'regex:/'.$field['pattern'].'/';
            }
            if ($field['unique'] ?? false) {
                $unique = Rule::unique($catalogo, $column);
                if ($record) {
                    $unique->ignore($record->{$definition['key']}, $definition['key']);
                }
                $rules[$column][] = $unique;
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return array_map(fn (array $field): string => $field['label'], Catalogo::definition($this->route('catalogo'))['fields']);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'integer' => 'Selecciona una opción válida para :attribute.',
            'exists' => 'La opción de :attribute no existe o fue eliminada.',
            'unique' => 'El valor de :attribute ya está registrado, incluso si fue eliminado.',
            'cp.regex' => 'El código postal debe contener exactamente cinco dígitos.',
        ];
    }
}
