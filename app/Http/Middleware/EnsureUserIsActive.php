<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks write operations (POST, PUT, PATCH, DELETE) for inactive accounts.
 *
 * An inactive user can still read (GET, HEAD, OPTIONS) and log in, but cannot
 * create, update, or delete any resource. This prevents data modification by
 * accounts that have been deactivated for any reason.
 */
class EnsureUserIsActive
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isWriteRequest($request)) {
            $user = $request->user();

            if ($user instanceof User && ! $user->is_active) {
                abort(Response::HTTP_FORBIDDEN, 'Your account is inactive and cannot perform this action.');
            }
        }

        return $next($request);
    }

    private function isWriteRequest(Request $request): bool
    {
        return ! in_array($request->getMethod(), self::READ_METHODS, true);
    }
}
