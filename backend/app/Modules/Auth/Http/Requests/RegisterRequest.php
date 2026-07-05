<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Le nom de l\'entreprise est requis.',
            'name.required'         => 'Votre nom est requis.',
            'email.required'        => 'L\'adresse email est requise.',
            'email.unique'          => 'Cette adresse email est déjà utilisée.',
            'password.required'     => 'Le mot de passe est requis.',
        ];
    }
}
