<?php

declare(strict_types=1);

namespace App\Http\Requests\SetPassword;

use Illuminate\Foundation\Http\FormRequest;

class SendSetPasswordLinkRequest extends FormRequest
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
             * The account email to send the set-password link to.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email'],
        ];
    }
}
