<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changement de numéro par le client : le numéro sert d'identifiant de
 * connexion, le mot de passe actuel est donc exigé.
 */
class UpdateCustomerPhoneRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/', Rule::unique('customers', 'phone')->ignore($this->user()->id)],
            'current_password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Indiquez votre nouveau numéro.',
            'phone.regex' => 'Le numéro doit être au format international, ex. +2250708124400.',
            'phone.unique' => 'Un compte existe déjà avec ce numéro.',
            'current_password.required' => 'Indiquez votre mot de passe actuel.',
        ];
    }
}
