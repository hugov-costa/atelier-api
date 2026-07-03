<?php

declare(strict_types=1);

namespace App\Http\Requests\SetPassword;

use Illuminate\Foundation\Http\FormRequest;

class ValidateSetPasswordTokenRequest extends FormRequest
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
            /**
             * The account email tied to the token.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email'],

            /**
             * The set-password token from the email link.
             *
             * @example 7b1f0c2e9a4d5f6071829abc3d4e5f60
             */
            'token' => ['required', 'string'],
        ];
    }
}
