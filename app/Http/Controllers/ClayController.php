<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Clay\StoreClayRequest;
use App\Http\Requests\Clay\UpdateClayRequest;
use App\Http\Resources\ClayResource;
use App\Http\Responses\ApiResponse;
use App\Models\Clay;
use App\Services\ClayService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Clays', weight: 21)]
class ClayController extends Controller
{
    public function __construct(private ClayService $clays) {}

    /**
     * Delete a clay
     */
    public function destroy(Clay $clay): Response
    {
        $this->authorize('delete', $clay);

        $this->clays->delete($clay);

        return response()->noContent();
    }

    /**
     * List clays
     *
     * Returns a paginated collection of clays. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Clay::class);

        return ClayResource::collection($this->clays->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a clay
     *
     * Restores a soft-deleted clay. Restricted to staff.
     */
    public function restore(Clay $clay): JsonResponse
    {
        $this->authorize('restore', $clay);

        return ApiResponse::item(new ClayResource($this->clays->restore($clay)));
    }

    /**
     * Show a clay
     */
    public function show(Clay $clay): JsonResponse
    {
        $this->authorize('view', $clay);

        return ApiResponse::item(new ClayResource($clay->load('claySupplier')));
    }

    /**
     * Create a clay
     */
    public function store(StoreClayRequest $request): JsonResponse
    {
        $this->authorize('create', Clay::class);

        $clay = $this->clays->create($request->validated());

        return ApiResponse::item(new ClayResource($clay->load('claySupplier')), status: Response::HTTP_CREATED);
    }

    /**
     * Update a clay
     */
    public function update(UpdateClayRequest $request, Clay $clay): JsonResponse
    {
        $this->authorize('update', $clay);

        $clay = $this->clays->update($request->validated(), $clay);

        return ApiResponse::item(new ClayResource($clay));
    }
}
