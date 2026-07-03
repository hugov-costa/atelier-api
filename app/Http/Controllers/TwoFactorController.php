<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Password\ConfirmPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\TwoFactorService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

#[Group('Two-factor authentication', weight: 5)]
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    /**
     * Enable two-factor authentication
     *
     * Provisions a TOTP secret and one-time recovery codes, returning the secret, an `otpauth://`
     * QR URL and the recovery codes. Two-factor is not active until confirmed. Requires the current
     * `password` (step-up auth): provisioning resets any existing factor, so a hijacked session must
     * not be able to strip an active second factor by re-enabling.
     */
    public function enable(ConfirmPasswordRequest $request): JsonResponse
    {
        return ApiResponse::item($this->twoFactor->enable($this->user($request)));
    }

    /**
     * Confirm two-factor authentication
     *
     * Activates two-factor by verifying a current TOTP `code` against the provisioned secret.
     */
    public function confirm(Request $request): Response
    {
        $request->validate([
            // A TOTP code from the authenticator app.
            'code' => ['required', 'string'],
        ]);

        if (! $this->twoFactor->confirm($this->user($request), (string) $request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => ['The provided two-factor code is invalid.'],
            ]);
        }

        return response()->noContent();
    }

    /**
     * Regenerate recovery codes
     *
     * Issues a fresh set of one-time recovery codes for an account with two-factor enabled,
     * invalidating the previous ones. Requires the current `password` (step-up auth).
     */
    public function regenerateRecoveryCodes(ConfirmPasswordRequest $request): JsonResponse
    {
        $user = $this->user($request);

        if (! $user->hasEnabledTwoFactor()) {
            abort(Response::HTTP_CONFLICT, 'Two-factor authentication is not enabled.');
        }

        return ApiResponse::item([
            'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user),
        ]);
    }

    /**
     * Disable two-factor authentication
     *
     * Removes the TOTP secret and recovery codes from the account. Requires the current
     * `password` (step-up auth) so a hijacked session cannot strip the second factor.
     */
    public function disable(ConfirmPasswordRequest $request): Response
    {
        $this->twoFactor->disable($this->user($request));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
