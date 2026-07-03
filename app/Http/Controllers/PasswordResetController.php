<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Password\ForgotPasswordRequest;
use App\Http\Requests\Password\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

#[Group('Password reset', weight: 2)]
class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $passwords) {}

    /**
     * Request a password reset link
     *
     * Emails a reset link if the address belongs to an account. Always responds with `204` so the
     * endpoint cannot be used to discover which emails are registered.
     */
    public function sendResetLink(ForgotPasswordRequest $request): Response
    {
        /** @var array<string, mixed> $credentials */
        $credentials = $request->only('email');

        $this->passwords->sendResetLink($credentials);

        return response()->noContent();
    }

    /**
     * Reset the password
     *
     * Sets a new password using the token from the reset email. On success every existing token
     * for the account is revoked.
     */
    public function reset(ResetPasswordRequest $request): Response
    {
        /** @var array<string, mixed> $credentials */
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $status = $this->passwords->reset($credentials);

        if (! $this->passwords->wasReset($status)) {
            throw ValidationException::withMessages([
                'email' => ['The password reset token is invalid or has expired.'],
            ]);
        }

        return response()->noContent();
    }
}
