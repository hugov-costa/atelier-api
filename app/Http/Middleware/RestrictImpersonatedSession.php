<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detects an active impersonation session and confines it. While a master acts as
 * another user, identity- and credential-changing actions are blocked, and every
 * write is tagged with the impersonator so the audit trail stays accountable.
 */
class RestrictImpersonatedSession
{
    /**
     * Route names a master may not trigger while impersonating another user.
     *
     * @var list<string>
     */
    private const RESTRICTED_ROUTES = [
        'user.password',
        'account.export',
        'account.destroy',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.recovery',
        'two-factor.disable',
        'users.destroy',
        'users.erase',
        'users.restore',
        'impersonation.start',
    ];

    public function __construct(private ImpersonationService $impersonation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            $active = $this->impersonation->activeFor($token);

            if ($active !== null) {
                $request->attributes->set('impersonator_id', $active->impersonator?->ulid);

                if (in_array($request->route()?->getName(), self::RESTRICTED_ROUTES, true)) {
                    abort(Response::HTTP_FORBIDDEN, 'This action is not allowed while impersonating another user.');
                }
            }
        }

        return $next($request);
    }
}
