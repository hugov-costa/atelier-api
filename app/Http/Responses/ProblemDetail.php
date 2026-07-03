<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds RFC 9457 "Problem Details" responses (application/problem+json).
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9457
 */
class ProblemDetail
{
    private const TYPE_BASE = 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/';

    private const TITLES = [
        419 => 'CSRF Token Mismatch',
    ];

    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string|list<string|null>>  $headers  Additional HTTP headers to include (e.g. Retry-After, Allow)
     */
    public static function response(int $status, ?string $detail = null, array $extensions = [], array $headers = []): JsonResponse
    {
        $title = self::TITLES[$status] ?? Response::$statusTexts[$status] ?? 'Unknown Error';

        /** @var array<string, mixed> $payload */
        $payload = array_merge([
            'type'   => self::TYPE_BASE.$status,
            'title'  => $title,
            'status' => $status,
            'detail' => $detail ?? $title,
        ], $extensions);

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = array_merge(
            ['Content-Type' => 'application/problem+json'],
            $headers,
        );

        return new JsonResponse($payload, $status, $responseHeaders);
    }
}
