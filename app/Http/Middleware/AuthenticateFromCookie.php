<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridges the httpOnly token cookie into the Authorization header so Sanctum can
 * authenticate browser clients while still accepting Bearer tokens directly. When
 * an impersonation cookie is present it takes precedence, so a master temporarily
 * acts as the target user without losing their own session cookie.
 */
class AuthenticateFromCookie
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('Authorization')) {
            $token = $this->cookieToken($request, 'auth.impersonation.cookie', 'impersonate_token')
                ?? $this->cookieToken($request, 'auth.token_cookie.name', 'access_token');

            if ($token !== null) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }

    private function cookieToken(Request $request, string $configKey, string $default): ?string
    {
        $name = config($configKey, $default);
        $token = $request->cookie(is_string($name) && $name !== '' ? $name : $default);

        return is_string($token) && $token !== '' ? $token : null;
    }
}
