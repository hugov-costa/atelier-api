<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SingleClass\StoreSingleClassRequest;
use App\Http\Requests\SingleClass\UpdateSingleClassRequest;
use App\Http\Resources\SingleClassResource;
use App\Http\Responses\ApiResponse;
use App\Models\SingleClass;
use App\Services\SingleClassService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Single classes', weight: 40)]
class SingleClassController extends Controller
{
    public function __construct(private SingleClassService $classes) {}

    /**
     * Delete a single class
     */
    public function destroy(SingleClass $singleClass): Response
    {
        $this->authorize('delete', $singleClass);

        $this->classes->delete($singleClass);

        return response()->noContent();
    }

    /**
     * List single classes
     *
     * Returns a paginated collection of one-off classes, most recent first. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SingleClass::class);

        return SingleClassResource::collection($this->classes->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a single class
     *
     * Restores a soft-deleted single class. Restricted to staff.
     */
    public function restore(SingleClass $singleClass): JsonResponse
    {
        $this->authorize('restore', $singleClass);

        return ApiResponse::item(new SingleClassResource($this->classes->restore($singleClass)));
    }

    /**
     * Show a single class
     */
    public function show(SingleClass $singleClass): JsonResponse
    {
        $this->authorize('view', $singleClass);

        return ApiResponse::item(new SingleClassResource($singleClass->load('users')));
    }

    /**
     * Create a single class
     */
    public function store(StoreSingleClassRequest $request): JsonResponse
    {
        $this->authorize('create', SingleClass::class);

        $class = $this->classes->create($request->validated());

        return ApiResponse::item(new SingleClassResource($class), status: Response::HTTP_CREATED);
    }

    /**
     * Update a single class
     */
    public function update(UpdateSingleClassRequest $request, SingleClass $singleClass): JsonResponse
    {
        $this->authorize('update', $singleClass);

        $class = $this->classes->update($request->validated(), $singleClass);

        return ApiResponse::item(new SingleClassResource($class));
    }
}
