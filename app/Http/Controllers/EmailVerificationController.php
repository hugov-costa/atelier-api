<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailVerificationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Email verification', weight: 6)]
class EmailVerificationController extends Controller
{
    public function __construct(private EmailVerificationService $verification) {}

    /**
     * Verify an email address
     *
     * Confirms ownership of the email using the signed link sent on registration. The `id` and
     * `hash` come from that link and the URL signature must be valid.
     */
    public function verify(EmailVerificationRequest $request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->verification->verify($user);
        }

        return response()->noContent();
    }

    /**
     * Resend the verification email
     *
     * Queues a new verification email for the authenticated user.
     */
    public function resend(Request $request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->verification->resend($user);
        }

        return response()->noContent();
    }
}
