<?php

namespace App\Http\Requests;

use App\Support\ImageOptimizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerPhotoRequest extends FormRequest
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
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width='.ImageOptimizer::MAX_DIMENSION.',max_height='.ImageOptimizer::MAX_DIMENSION],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photo.dimensions' => 'Cette image est trop grande ('.ImageOptimizer::MAX_DIMENSION.' × '.ImageOptimizer::MAX_DIMENSION.' pixels maximum).',
        ];
    }
}
