<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * The atelier logo image (jpeg, png or webp), up to 2 MB and 5000x5000 px.
             */
            'logo' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:2048',
                Rule::dimensions()->maxWidth(5000)->maxHeight(5000),
            ],
        ];
    }
}
