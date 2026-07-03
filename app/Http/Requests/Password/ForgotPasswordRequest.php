<?php

declare(strict_types=1);

namespace App\Http\Requests\Password;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
             * The email address to send the password reset link to.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email'],
        ];
    }
}
