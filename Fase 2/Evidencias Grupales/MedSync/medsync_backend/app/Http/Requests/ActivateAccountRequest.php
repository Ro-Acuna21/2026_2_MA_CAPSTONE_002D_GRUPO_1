<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(
                trim((string) $this->input('email'))
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:150',
            ],

            'token' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/\d/',
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',

            'token.required' => 'El token de activación es obligatorio.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener mayúscula, minúscula y número.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}