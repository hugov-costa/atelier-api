<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Password\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Authentication', weight: 1)]
class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    /**
     * Log in
     *
     * Authenticates with email and password. When two-factor authentication is enabled for the
     * account, a valid TOTP or recovery `code` must also be supplied.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->authenticate(
            (string) $request->string('email'),
            (string) $request->string('password'),
            (string) $request->string('code'),
        );

        return $this->tokenResponse($user, Response::HTTP_OK);
    }

    /**
     * Get the authenticated user
     *
     * Returns the profile of the user owning the current token.
     */
    public function user(Request $request): JsonResponse
    {
        return ApiResponse::item(new UserResource($request->user()));
    }

    /**
     * Change the password
     *
     * Updates the authenticated user's password after verifying the current one. Every other token
     * is revoked; the token used for this request stays valid.
     */
    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->auth->changePassword($user, (string) $request->string('password'));
        }

        return response()->noContent();
    }

    /**
     * Log out
     *
     * Revokes the token used for the current request and clears the auth cookies.
     */
    public function logout(Request $request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->auth->revokeCurrentToken($user);
        }

        return response()->noContent()
            ->withCookie($this->auth->forgetTokenCookie())
            ->withCookie($this->auth->forgetCsrfCookie());
    }

    private function tokenResponse(User $user, int $status): JsonResponse
    {
        $token = $this->auth->issueToken($user);

        return ApiResponse::item([
            'user'       => new UserResource($user),
            'token'      => $token,
            'token_type' => 'Bearer',
        ], status: $status)
            ->withCookie($this->auth->tokenCookie($token))
            ->withCookie($this->auth->csrfCookie());
    }
}
