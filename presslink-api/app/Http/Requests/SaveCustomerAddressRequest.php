<?php

namespace App\Http\Requests;

use App\Support\Communes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Adresse de ramassage et de livraison du client (création ou modification). */
class SaveCustomerAddressRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:40'],
            'commune' => ['required', 'string', Rule::in(Communes::all())],
            'neighborhood' => ['nullable', 'string', 'max:80'],
            'landmark' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'commune.in' => 'Choisissez une commune de la liste.',
            'landmark.required' => 'Indiquez un repère pour que le coursier vous trouve.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['label' => 'nom de l\'adresse', 'neighborhood' => 'quartier', 'landmark' => 'repère'];
    }
}
