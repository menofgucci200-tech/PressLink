<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Première étape de l'auth client : on vérifie si un compte existe déjà
 * pour ce numéro, afin d'orienter vers connexion ou inscription.
 */
class CheckPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Le numéro doit être au format international, ex. +255712345678.',
        ];
    }
}
