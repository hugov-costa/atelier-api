<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FiringCycle\StoreFiringCycleRequest;
use App\Http\Requests\FiringCycle\UpdateFiringCycleRequest;
use App\Http\Resources\FiringCycleResource;
use App\Http\Responses\ApiResponse;
use App\Models\FiringCycle;
use App\Services\FiringCycleService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Firing cycles', weight: 25)]
class FiringCycleController extends Controller
{
    public function __construct(private FiringCycleService $cycles) {}

    /**
     * Delete a firing cycle
     */
    public function destroy(FiringCycle $firingCycle): Response
    {
        $this->authorize('delete', $firingCycle);

        $this->cycles->delete($firingCycle);

        return response()->noContent();
    }

    /**
     * List firing cycles
     *
     * Returns a paginated collection of firing cycles. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FiringCycle::class);

        return FiringCycleResource::collection($this->cycles->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a firing cycle
     *
     * Restores a soft-deleted firing cycle. Restricted to staff.
     */
    public function restore(FiringCycle $firingCycle): JsonResponse
    {
        $this->authorize('restore', $firingCycle);

        return ApiResponse::item(new FiringCycleResource($this->cycles->restore($firingCycle)));
    }

    /**
     * Show a firing cycle
     */
    public function show(FiringCycle $firingCycle): JsonResponse
    {
        $this->authorize('view', $firingCycle);

        return ApiResponse::item(new FiringCycleResource($firingCycle));
    }

    /**
     * Create a firing cycle
     */
    public function store(StoreFiringCycleRequest $request): JsonResponse
    {
        $this->authorize('create', FiringCycle::class);

        $cycle = $this->cycles->create($request->validated());

        return ApiResponse::item(new FiringCycleResource($cycle), status: Response::HTTP_CREATED);
    }

    /**
     * Update a firing cycle
     */
    public function update(UpdateFiringCycleRequest $request, FiringCycle $firingCycle): JsonResponse
    {
        $this->authorize('update', $firingCycle);

        $cycle = $this->cycles->update($request->validated(), $firingCycle);

        return ApiResponse::item(new FiringCycleResource($cycle));
    }
}
