<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GlazeSupplier\StoreGlazeSupplierRequest;
use App\Http\Requests\GlazeSupplier\UpdateGlazeSupplierRequest;
use App\Http\Resources\GlazeSupplierResource;
use App\Http\Responses\ApiResponse;
use App\Models\GlazeSupplier;
use App\Services\GlazeSupplierService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Glaze suppliers', weight: 22)]
class GlazeSupplierController extends Controller
{
    public function __construct(private GlazeSupplierService $suppliers) {}

    /**
     * Delete a glaze supplier
     *
     * Removes the supplier and cascades to its glazes.
     */
    public function destroy(GlazeSupplier $glazeSupplier): Response
    {
        $this->authorize('delete', $glazeSupplier);

        $this->suppliers->delete($glazeSupplier);

        return response()->noContent();
    }

    /**
     * List glaze suppliers
     *
     * Returns a paginated collection of glaze suppliers. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GlazeSupplier::class);

        return GlazeSupplierResource::collection($this->suppliers->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a glaze supplier
     *
     * Restores a soft-deleted glaze supplier and cascades to its glazes. Restricted to staff.
     */
    public function restore(GlazeSupplier $glazeSupplier): JsonResponse
    {
        $this->authorize('restore', $glazeSupplier);

        return ApiResponse::item(new GlazeSupplierResource($this->suppliers->restore($glazeSupplier)));
    }

    /**
     * Show a glaze supplier
     */
    public function show(GlazeSupplier $glazeSupplier): JsonResponse
    {
        $this->authorize('view', $glazeSupplier);

        return ApiResponse::item(new GlazeSupplierResource($glazeSupplier));
    }

    /**
     * Create a glaze supplier
     */
    public function store(StoreGlazeSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', GlazeSupplier::class);

        $supplier = $this->suppliers->create($request->validated());

        return ApiResponse::item(new GlazeSupplierResource($supplier), status: Response::HTTP_CREATED);
    }

    /**
     * Update a glaze supplier
     */
    public function update(UpdateGlazeSupplierRequest $request, GlazeSupplier $glazeSupplier): JsonResponse
    {
        $this->authorize('update', $glazeSupplier);

        $supplier = $this->suppliers->update($request->validated(), $glazeSupplier);

        return ApiResponse::item(new GlazeSupplierResource($supplier));
    }
}
