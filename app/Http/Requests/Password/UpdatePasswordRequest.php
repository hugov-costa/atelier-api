<?php

declare(strict_types=1);

namespace App\Http\Requests\Password;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Your current password, used to confirm the change.
             *
             * @example S3cret!pass
             */
            'current_password' => ['required', 'string', $this->matchesCurrentPassword()],

            /**
             * New password: at least 8 characters with upper- and lower-case letters, a number and a symbol.
             * Confirm it with `password_confirmation`.
             *
             * @example N0va!senha
             */
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    protected function matchesCurrentPassword(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = $this->user();

            if (! $user instanceof User || ! is_string($value) || ! is_string($user->password)
                || ! Hash::check($value, $user->password)) {
                $fail('The provided password does not match your current password.');
            }
        };
    }
}
