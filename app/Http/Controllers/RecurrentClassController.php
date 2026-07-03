<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RecurrentClass\StoreRecurrentClassRequest;
use App\Http\Requests\RecurrentClass\UpdateRecurrentClassRequest;
use App\Http\Resources\RecurrentClassResource;
use App\Http\Responses\ApiResponse;
use App\Models\RecurrentClass;
use App\Services\RecurrentClassService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Recurrent classes', weight: 41)]
class RecurrentClassController extends Controller
{
    public function __construct(private RecurrentClassService $classes) {}

    /**
     * Delete a recurrent class
     */
    public function destroy(RecurrentClass $recurrentClass): Response
    {
        $this->authorize('delete', $recurrentClass);

        $this->classes->delete($recurrentClass);

        return response()->noContent();
    }

    /**
     * List recurrent classes
     *
     * Returns a paginated collection of weekly class slots. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RecurrentClass::class);

        return RecurrentClassResource::collection($this->classes->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a recurrent class
     *
     * Restores a soft-deleted recurrent class. Restricted to staff.
     */
    public function restore(RecurrentClass $recurrentClass): JsonResponse
    {
        $this->authorize('restore', $recurrentClass);

        return ApiResponse::item(new RecurrentClassResource($this->classes->restore($recurrentClass)));
    }

    /**
     * Show a recurrent class
     */
    public function show(RecurrentClass $recurrentClass): JsonResponse
    {
        $this->authorize('view', $recurrentClass);

        return ApiResponse::item(new RecurrentClassResource($recurrentClass->load('users')));
    }

    /**
     * Create a recurrent class
     */
    public function store(StoreRecurrentClassRequest $request): JsonResponse
    {
        $this->authorize('create', RecurrentClass::class);

        $class = $this->classes->create($request->validated());

        return ApiResponse::item(new RecurrentClassResource($class), status: Response::HTTP_CREATED);
    }

    /**
     * Update a recurrent class
     */
    public function update(UpdateRecurrentClassRequest $request, RecurrentClass $recurrentClass): JsonResponse
    {
        $this->authorize('update', $recurrentClass);

        $class = $this->classes->update($request->validated(), $recurrentClass);

        return ApiResponse::item(new RecurrentClassResource($class));
    }
}
