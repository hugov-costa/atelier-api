<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SetPassword\ConfirmSetPasswordRequest;
use App\Http\Requests\SetPassword\SendSetPasswordLinkRequest;
use App\Http\Requests\SetPassword\ValidateSetPasswordTokenRequest;
use App\Models\User;
use App\Services\SetPasswordService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

#[Group('Set password', weight: 4)]
class SetPasswordController extends Controller
{
    public function __construct(private SetPasswordService $setPassword) {}

    /**
     * Request a set-password link
     *
     * Emails a link when the address belongs to an account that has not set a password yet.
     * Always responds `204` so the endpoint cannot reveal which emails are registered.
     *
     * @unauthenticated
     */
    public function request(SendSetPasswordLinkRequest $request): Response
    {
        $user = User::query()->where('email', (string) $request->string('email'))->first();

        if ($user instanceof User) {
            $this->setPassword->sendLink($user);
        }

        return response()->noContent();
    }

    /**
     * Validate a set-password token
     *
     * Confirms a token is still valid before the frontend shows the password form.
     *
     * @unauthenticated
     */
    public function validateToken(ValidateSetPasswordTokenRequest $request): Response
    {
        if (! $this->setPassword->tokenIsValid(
            (string) $request->string('email'),
            (string) $request->string('token'),
        )) {
            throw ValidationException::withMessages([
                'token' => ['The set-password token is invalid or has expired.'],
            ]);
        }

        return response()->noContent();
    }

    /**
     * Set the password
     *
     * Sets the account's first password using the token from the email.
     *
     * @unauthenticated
     */
    public function confirm(ConfirmSetPasswordRequest $request): Response
    {
        $ok = $this->setPassword->confirm(
            (string) $request->string('email'),
            (string) $request->string('token'),
            (string) $request->string('password'),
        );

        if (! $ok) {
            throw ValidationException::withMessages([
                'token' => ['The set-password token is invalid or has expired.'],
            ]);
        }

        return response()->noContent();
    }
}
