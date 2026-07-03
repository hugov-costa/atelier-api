<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Builds the standard success envelope ({ data, message }) used by every 2xx
 * JSON response. Errors are emitted as RFC 9457 problem+json by ProblemDetail.
 */
class ApiResponse
{
    public static function item(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'data'    => $data,
            'message' => $message,
        ], $status);
    }
}
