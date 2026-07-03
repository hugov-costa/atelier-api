<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    /**
     * Send a password reset link.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function sendResetLink(array $credentials): void
    {
        Password::sendResetLink($credentials);
    }

    /**
     * Reset the password and revoke every existing token for the user.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function reset(array $credentials): string
    {
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));
        });

        return is_string($status) ? $status : Password::INVALID_TOKEN;
    }

    public function wasReset(string $status): bool
    {
        return $status === Password::PASSWORD_RESET;
    }
}
