<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Demande de ramassage envoyée depuis l'appli client. */
class StorePickupRequestRequest extends FormRequest
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
            'pressing_id' => ['required', 'integer'],
            'address_id' => ['required', 'integer'],
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'slot_from' => ['required', 'date_format:H:i'],
            'slot_to' => ['required', 'date_format:H:i'],
            'items_estimate' => ['nullable', 'integer', 'min:1', 'max:200'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'address_id.required' => 'Choisissez l\'adresse où passer.',
            'pickup_date.required' => 'Choisissez le jour du ramassage.',
            'slot_from.required' => 'Choisissez le créneau du ramassage.',
            'items_estimate.max' => 'Pour plus de 200 pièces, appelez directement le pressing.',
            'note.max' => 'Le message ne doit pas dépasser 500 caractères.',
        ];
    }
}
