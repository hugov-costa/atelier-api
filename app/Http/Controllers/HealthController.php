<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\HealthService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Health', weight: 0)]
class HealthController extends Controller
{
    public function __construct(private HealthService $health) {}

    /**
     * Service health
     *
     * Readiness probe that verifies connectivity to the database, Redis and object storage.
     * Returns `200` when every dependency is reachable and `503` when any of them is degraded.
     * Failure reasons are written to the application log, never exposed in the response.
     */
    public function show(): JsonResponse
    {
        $report = $this->health->check();

        $status = $report['status'] === 'ok'
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE;

        return ApiResponse::item($report, status: $status);
    }
}
