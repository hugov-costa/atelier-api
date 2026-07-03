<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Impersonation\StartImpersonationRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ImpersonationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Impersonation', weight: 3)]
class ImpersonationController extends Controller
{
    public function __construct(
        private ImpersonationService $impersonation,
        private AuthService $auth,
    ) {}

    /**
     * Start impersonating a user
     *
     * Lets a master temporarily act as another (non-master) user for support, carried by a
     * time-boxed httpOnly cookie. A reason is required and the access is recorded for audit.
     */
    public function start(StartImpersonationRequest $request, User $user): JsonResponse
    {
        $master = $request->user();

        if (! $master instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $result = $this->impersonation->start(
            $master,
            $user,
            (string) $request->string('reason'),
            $request->ip(),
        );

        return ApiResponse::item([
            'user'       => new UserResource($user),
            'expires_at' => $result['impersonation']->expires_at->toIso8601String(),
        ])->withCookie(
            $this->auth->impersonationCookie($result['token'], $this->impersonation->ttlMinutes())
        );
    }

    /**
     * Stop impersonating
     *
     * Ends the active impersonation session, revokes its token and clears the impersonation
     * cookie, returning the master to their own account.
     */
    public function stop(Request $request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->impersonation->stop($user);
        }

        return response()->noContent()
            ->withCookie($this->auth->forgetImpersonationCookie());
    }
}
