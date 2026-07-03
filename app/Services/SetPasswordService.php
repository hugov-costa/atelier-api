<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Password;

/**
 * Onboarding flow for accounts created without a password. It reuses the
 * framework's dedicated "set_password" broker so tokens are hashed at rest,
 * expire and are single-use — the same guarantees as password reset.
 */
class SetPasswordService
{
    /**
     * Issue a set-password token and email the link. No-op (silently) when the
     * account already has a password, so the flow cannot be used to hijack an
     * established account.
     */
    public function sendLink(User $user): void
    {
        if ($user->password !== null) {
            return;
        }

        if ($this->repository()->recentlyCreatedToken($user)) {
            return;
        }

        $token = $this->repository()->create($user);

        $user->notify(new SetPasswordNotification($token, $user->email));
    }

    public function tokenIsValid(string $email, string $token): bool
    {
        $user = User::query()->where('email', $email)->first();

        return $user instanceof User && $user->password === null && $this->repository()->exists($user, $token);
    }

    /**
     * Complete onboarding: set the password, verify the email, revoke any tokens
     * and consume the set-password token. Returns false when the token is
     * invalid or the account already has a password.
     */
    public function confirm(string $email, string $token, string $password): bool
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || $user->password !== null || ! $this->repository()->exists($user, $token)) {
            return false;
        }

        $user->forceFill([
            'password'          => $password,
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ])->save();

        $user->tokens()->delete();
        $this->repository()->delete($user);

        return true;
    }

    private function repository(): TokenRepositoryInterface
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker('set_password');

        return $broker->getRepository();
    }
}
