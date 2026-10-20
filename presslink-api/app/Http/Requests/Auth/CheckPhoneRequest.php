<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesPhone;
use App\Rules\IvorianPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Première étape de l'auth client : on vérifie si un compte existe déjà
 * pour ce numéro, afin d'orienter vers connexion ou inscription.
 */
class CheckPhoneRequest extends FormRequest
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
            'phone' => ['required', 'string', new IvorianPhone],
        ];
    }
}
