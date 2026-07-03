<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Glaze\StoreGlazeRequest;
use App\Http\Requests\Glaze\UpdateGlazeRequest;
use App\Http\Resources\GlazeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Glaze;
use App\Services\GlazeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Glazes', weight: 23)]
class GlazeController extends Controller
{
    public function __construct(private GlazeService $glazes) {}

    /**
     * Delete a glaze
     */
    public function destroy(Glaze $glaze): Response
    {
        $this->authorize('delete', $glaze);

        $this->glazes->delete($glaze);

        return response()->noContent();
    }

    /**
     * List glazes
     *
     * Returns a paginated collection of glazes. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Glaze::class);

        return GlazeResource::collection($this->glazes->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a glaze
     *
     * Restores a soft-deleted glaze. Restricted to staff.
     */
    public function restore(Glaze $glaze): JsonResponse
    {
        $this->authorize('restore', $glaze);

        return ApiResponse::item(new GlazeResource($this->glazes->restore($glaze)));
    }

    /**
     * Show a glaze
     */
    public function show(Glaze $glaze): JsonResponse
    {
        $this->authorize('view', $glaze);

        return ApiResponse::item(new GlazeResource($glaze->load('glazeSupplier')));
    }

    /**
     * Create a glaze
     */
    public function store(StoreGlazeRequest $request): JsonResponse
    {
        $this->authorize('create', Glaze::class);

        $glaze = $this->glazes->create($request->validated());

        return ApiResponse::item(new GlazeResource($glaze->load('glazeSupplier')), status: Response::HTTP_CREATED);
    }

    /**
     * Update a glaze
     */
    public function update(UpdateGlazeRequest $request, Glaze $glaze): JsonResponse
    {
        $this->authorize('update', $glaze);

        $glaze = $this->glazes->update($request->validated(), $glaze);

        return ApiResponse::item(new GlazeResource($glaze));
    }
}
