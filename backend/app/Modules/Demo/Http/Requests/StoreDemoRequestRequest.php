<?php

namespace App\Modules\Demo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemoRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route publique
    }

    public function rules(): array
    {
        return [
            'first_name'         => ['nullable', 'string', 'max:100'],
            'last_name'          => ['nullable', 'string', 'max:100'],
            'email'              => ['required', 'email:rfc', 'max:190'],
            'phone'              => ['nullable', 'string', 'max:40'],
            'company'            => ['nullable', 'string', 'max:150'],
            'country'            => ['nullable', 'string', 'size:2'],
            'primary_need'       => ['nullable', 'string', 'max:150'],
            'modules'            => ['nullable', 'array', 'max:30'],
            'modules.*'          => ['string', 'max:50'],
            'message'            => ['nullable', 'string', 'max:2000'],
            'consent_contact'    => ['accepted'], // case obligatoire
            'consent_demo_email' => ['boolean'],
            'source'             => ['nullable', 'string', 'max:60'],
            'locale'             => ['nullable', Rule::in(['fr', 'en'])],

            // Honeypot anti-bot : champ invisible côté UI, DOIT rester vide.
            'website'            => ['nullable', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent_contact.accepted' => 'Le consentement est requis pour traiter votre demande.',
            'website.prohibited'       => 'Soumission invalide.',
        ];
    }
}
