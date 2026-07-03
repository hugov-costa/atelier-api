<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ProblemDetail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps every thrown exception to an RFC 9457 problem+json response, hiding
 * internal details (stack traces, SQL, …) unless the application is in debug.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e, bool $debug): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return ProblemDetail::response(
                $e->status,
                'The given data failed validation.',
                ['errors' => $e->errors()],
            );
        }

        if ($e instanceof AuthenticationException) {
            return ProblemDetail::response(401, 'Authentication is required to access this resource.');
        }

        if ($e instanceof AuthorizationException) {
            return ProblemDetail::response(403, $e->getMessage() !== '' ? $e->getMessage() : null);
        }

        if ($e instanceof ModelNotFoundException) {
            return ProblemDetail::response(404, 'The requested resource was not found.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            /** @var array<string, string|list<string|null>> $headers */
            $headers = $e->getHeaders();

            return ProblemDetail::response($status, $e->getMessage() !== '' ? $e->getMessage() : null, headers: $headers);
        }

        $extensions = $debug ? ['debug' => ['exception' => $e::class, 'message' => $e->getMessage()]] : [];

        return ProblemDetail::response(
            500,
            'An unexpected error occurred while processing the request.',
            $extensions,
        );
    }
}
