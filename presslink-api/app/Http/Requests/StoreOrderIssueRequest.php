<?php

namespace App\Http\Requests;

use App\Enums\OrderIssueCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderIssueRequest extends FormRequest
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
            'category' => ['required', Rule::enum(OrderIssueCategory::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
