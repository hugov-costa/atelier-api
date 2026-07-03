<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $target = $this->route('user');
        $actor = $this->user();
        $ignore = $target instanceof User ? $target->getKey() : null;
        $isManager = $actor instanceof User && $actor->canManageUsers();

        return [
            /**
             * The user's full name.
             *
             * @example Jane Doe
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * A unique email address. Changing it marks the account unverified and sends a new verification email.
             *
             * @example jane@example.com
             */
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],

            /**
             * Contact phone with area code, digits only (10-11 chars).
             *
             * @example 11987654321
             */
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{10,11}$/'],

            /**
             * Birthday (YYYY-mm-dd).
             *
             * @example 2000-01-01
             */
            'birthday' => ['sometimes', 'nullable', 'date', 'before:today'],

            /**
             * Admission date (YYYY-mm-dd). Staff only.
             *
             * @example 2026-01-15
             */
            'admission_date' => $isManager ? ['sometimes', 'nullable', 'date'] : ['prohibited'],

            /**
             * Whether the account is active. Staff only.
             *
             * @example true
             */
            'is_active' => $isManager ? ['sometimes', 'boolean'] : ['prohibited'],

            /**
             * The user's role. Only a master may assign roles, and the last master cannot be demoted.
             *
             * @example admin
             */
            'role' => ['sometimes', Rule::enum(UserRole::class), $this->validateRoleChange($actor, $target)],

            /**
             * Required only when you change your own email address.
             *
             * @example S3cret!pass
             */
            'current_password' => $this->requiresCurrentPassword($actor, $target)
                ? ['required', 'string', $this->matchesPasswordOf($actor)]
                : ['nullable', 'string'],
        ];
    }

    private function requiresCurrentPassword(mixed $actor, mixed $target): bool
    {
        $isSelf = $actor instanceof User && $target instanceof User && $actor->getKey() === $target->getKey();
        $emailChanging = $target instanceof User && $this->has('email') && $this->input('email') !== $target->email;

        return $isSelf && $emailChanging;
    }

    private function matchesPasswordOf(mixed $actor): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($actor): void {
            if (! $actor instanceof User || ! is_string($value) || ! is_string($actor->password)
                || ! Hash::check($value, $actor->password)) {
                $fail('The provided password does not match your current password.');
            }
        };
    }

    private function validateRoleChange(mixed $actor, mixed $target): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($actor, $target): void {
            if (! $actor instanceof User || ! $actor->isMaster()) {
                $fail('Only a master may change a user\'s role.');

                return;
            }

            if ($target instanceof User && $target->isMaster() && $value !== UserRole::Master->value
                && $this->isLastMaster($target)) {
                $fail('The last master cannot be demoted.');
            }
        };
    }

    private function isLastMaster(User $target): bool
    {
        return User::query()
            ->where('role', UserRole::Master->value)
            ->whereKeyNot($target->getKey())
            ->doesntExist();
    }
}
