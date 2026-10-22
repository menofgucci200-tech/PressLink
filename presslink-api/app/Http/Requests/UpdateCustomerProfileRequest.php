<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Seul le réglage « Aussi par email » est envoyé (interrupteur du
     * profil) : le reste du profil n'est ni exigé ni modifié.
     */
    public function isPreferenceOnly(): bool
    {
        return $this->has('email_notifications')
            && ! $this->hasAny(['first_name', 'last_name', 'gender', 'email']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isPreferenceOnly()) {
            return ['email_notifications' => ['required', 'boolean']];
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'email_notifications' => ['sometimes', 'boolean'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($this->user()->id)],
        ];
    }
}
