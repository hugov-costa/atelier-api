<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Double-submit CSRF protection for cookie-authenticated, state-changing
 * requests. Requests carrying an explicit Bearer token (mobile/server clients)
 * are not CSRF-able and are skipped.
 */
class VerifyCookieCsrfToken
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->requiresVerification($request) && ! $this->tokensMatch($request)) {
            abort(419, 'CSRF token mismatch.');
        }

        return $next($request);
    }

    private function requiresVerification(Request $request): bool
    {
        if (in_array($request->getMethod(), self::READ_METHODS, true)) {
            return false;
        }

        if ($request->headers->has('Authorization')) {
            return false;
        }

        $name = config('auth.token_cookie.name', 'access_token');

        return $request->cookie(is_string($name) ? $name : 'access_token') !== null;
    }

    private function tokensMatch(Request $request): bool
    {
        $cookie = $request->cookie('__Host-XSRF-TOKEN') ?? $request->cookie('XSRF-TOKEN');
        $header = $request->header('X-XSRF-TOKEN');

        return is_string($cookie) && is_string($header) && $cookie !== '' && hash_equals($cookie, $header);
    }
}
