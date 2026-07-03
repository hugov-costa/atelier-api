<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * One-time bootstrap of the first master account. The service rejects the call
 * once any account exists, so this is only usable on an empty installation.
 */
class CreateMasterRequest extends FormRequest
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
             * The master's full name.
             *
             * @example Atelier Owner
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * A unique email address.
             *
             * @example owner@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            /**
             * The master password: at least 8 characters with upper- and lower-case letters, a number and a symbol.
             * Confirm it with `password_confirmation`.
             *
             * @example S3cret!pass
             */
            'password' => ['required', 'confirmed', Password::defaults()],

            /**
             * Contact phone with area code, digits only (10-11 chars).
             *
             * @example 11987654321
             */
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],

            /**
             * Birthday (YYYY-mm-dd).
             *
             * @example 1990-01-01
             */
            'birthday' => ['nullable', 'date', 'before:today'],

            /**
             * Admission date (YYYY-mm-dd).
             *
             * @example 2026-01-01
             */
            'admission_date' => ['nullable', 'date'],
        ];
    }
}
