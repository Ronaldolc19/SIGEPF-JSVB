<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCorrespondenciaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('destinatarios')) {
            $this->merge(['destinatarios' => []]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrador') && $this->user()->can($this->route('id_correspondencia') ? 'correspondencia.editar' : 'correspondencia.registrar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_tipo_correspondencia' => ['required', 'integer', Rule::exists('tipos_correspondencia', 'id_tipo_correspondencia')->whereNull('deleted_at')],
            'direccion' => ['required', Rule::in(['Recibida', 'Emitida'])],
            'numero_oficio' => ['nullable', 'string', 'max:100'], 'asunto' => ['required', 'string', 'max:255'],
            'remitente' => ['required', 'string', 'max:255'], 'destinatario_externo' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'fecha_documento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fecha_movimiento' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_documento', 'before_or_equal:today'],
            'requiere_respuesta' => ['required', 'boolean'], 'fecha_limite' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_movimiento'],
            'responsable_id' => ['required', 'integer'],
            'destinatarios' => ['present', 'array', 'max:2000'], 'destinatarios.*' => ['required', 'integer', 'distinct'],
            'archivo' => ['nullable', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240'],
            'id_adjunto_incidencia' => ['nullable', 'integer'],
            'anexos' => ['nullable', 'array', 'max:5'], 'anexos.*' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240'],
            'revision' => [$this->route('id_correspondencia') ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['required' => 'Completa este dato.', 'exists' => 'Selecciona una opción vigente.', 'mimes' => 'Adjunta un PDF válido.',
            'extensions' => 'El archivo debe tener extensión PDF.', 'archivo.max' => 'El PDF no debe superar 10 MB.', 'anexos.*.max' => 'Cada anexo admite hasta 10 MB.',
            'destinatarios.distinct' => 'No repitas destinatarios.', 'after_or_equal' => 'La fecha debe ser igual o posterior a la fecha de referencia.'];
    }
}
