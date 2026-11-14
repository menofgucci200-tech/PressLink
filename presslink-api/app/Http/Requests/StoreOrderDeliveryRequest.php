<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Livraison d'une commande prête, demandée depuis l'appli client. */
class StoreOrderDeliveryRequest extends FormRequest
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
            'address_id' => ['required', 'integer'],
            'delivery_date' => ['required', 'date_format:Y-m-d'],
            'slot_from' => ['required', 'date_format:H:i'],
            'slot_to' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'address_id.required' => 'Choisissez l\'adresse de livraison.',
            'delivery_date.required' => 'Choisissez le jour de la livraison.',
            'slot_from.required' => 'Choisissez le créneau de la livraison.',
            'note.max' => 'Le message ne doit pas dépasser 500 caractères.',
        ];
    }
}
