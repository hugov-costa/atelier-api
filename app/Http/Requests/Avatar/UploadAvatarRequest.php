<?php

declare(strict_types=1);

namespace App\Http\Requests\Avatar;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        $actor = $this->user();

        return $target instanceof User && $actor instanceof User && $actor->can('update', $target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * The image file (jpeg, png or webp), up to 2 MB and 5000×5000 px.
             */
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:max_width=5000,max_height=5000'],
        ];
    }
}
