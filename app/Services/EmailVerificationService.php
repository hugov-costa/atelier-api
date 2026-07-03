<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

class EmailVerificationService
{
    /**
     * Mark the user's email as verified and fire the Verified event.
     */
    public function verify(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }
    }

    /**
     * Resend the verification notification when the user is still unverified.
     */
    public function resend(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }
}
