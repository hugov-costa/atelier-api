<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);

        $requestId = is_string($incoming) && $incoming !== '' && strlen($incoming) <= 128
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set(self::HEADER, $requestId);
        $request->attributes->set('request_id', $requestId);

        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
