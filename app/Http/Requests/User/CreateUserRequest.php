<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * The person's full name.
             *
             * @example Jane Doe
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * A unique email address; the set-password link is sent here.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            /**
             * Contact phone with area code, digits only (10-11 chars).
             *
             * @example 11987654321
             */
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],

            /**
             * Birthday (YYYY-mm-dd).
             *
             * @example 2000-01-01
             */
            'birthday' => ['nullable', 'date', 'before:today'],

            /**
             * Admission date (YYYY-mm-dd). Defaults to today when omitted.
             *
             * @example 2026-01-15
             */
            'admission_date' => ['nullable', 'date'],

            /**
             * Whether the account is active.
             *
             * @example true
             */
            'is_active' => ['nullable', 'boolean'],

            /**
             * The role to assign. Only a master may assign `admin` or `master`; defaults to `user`.
             *
             * @example user
             */
            'role' => ['sometimes', Rule::enum(UserRole::class), $this->onlyMasterAssignsElevatedRole()],
        ];
    }

    private function onlyMasterAssignsElevatedRole(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === UserRole::User->value) {
                return;
            }

            $actor = $this->user();

            if (! $actor instanceof User || ! $actor->isMaster()) {
                $fail('Only a master may assign an elevated role.');
            }
        };
    }
}
