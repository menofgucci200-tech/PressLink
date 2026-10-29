<?php

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Http\Requests\Concerns\NormalizesPhone;
use App\Models\Customer;
use App\Rules\IvorianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCustomerRequest extends FormRequest
{
    use NormalizesPhone;

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
            // Un compte ouvert par un pressing (sans mot de passe) peut être réclamé.
            'phone' => ['required', 'string', new IvorianPhone, Rule::unique('customers', 'phone')->where('sandbox_pressing_id', 0)->whereNotNull('password')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($this->pendingCustomerId())],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ];
    }

    /** Compte ouvert par un pressing pour ce numéro, s'il existe. */
    private function pendingCustomerId(): ?int
    {
        return Customer::outsideSandboxes()->where('phone', (string) $this->input('phone'))->whereNull('password')->value('id');
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Un compte existe déjà avec ce numéro.',
            'password.min' => 'Le mot de passe doit contenir au moins 4 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
