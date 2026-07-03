<?php

declare(strict_types=1);

namespace App\Http\Requests\Password;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
             * The reset token from the password reset email.
             *
             * @example 7b1f0c2e9a4d5f6071829abc3d4e5f60
             */
            'token' => ['required', 'string'],

            /**
             * The email address tied to the reset token.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email'],

            /**
             * New password: at least 8 characters with upper- and lower-case letters, a number and a symbol.
             * Confirm it with `password_confirmation`.
             *
             * @example S3cret!pass
             */
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
