<?php

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Http\Requests\Concerns\NormalizesPhone;
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
            'phone' => ['required', 'string', new IvorianPhone, 'unique:customers,phone'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ];
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
