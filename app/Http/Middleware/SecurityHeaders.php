<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Content-Type-Options'            => 'nosniff',
        'X-Frame-Options'                   => 'DENY',
        'Referrer-Policy'                   => 'no-referrer',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Content-Security-Policy'           => "default-src 'none'; frame-ancestors 'none'",
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }
}
