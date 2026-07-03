<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
             * The account email address.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email'],

            /**
             * The account password.
             *
             * @example S3cret!pass
             */
            'password' => ['required', 'string'],

            /**
             * A TOTP or recovery code; required only when two-factor authentication is enabled.
             *
             * @example 123456
             */
            'code' => ['sometimes', 'string'],
        ];
    }
}
