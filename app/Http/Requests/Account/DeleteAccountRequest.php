<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class DeleteAccountRequest extends FormRequest
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
             * Your current password, required to confirm the irreversible account deletion.
             *
             * @example S3cret!pass
             */
            'password' => ['required', 'string', $this->matchesCurrentPassword()],
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
