<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClaySupplier\StoreClaySupplierRequest;
use App\Http\Requests\ClaySupplier\UpdateClaySupplierRequest;
use App\Http\Resources\ClaySupplierResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClaySupplier;
use App\Services\ClaySupplierService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Clay suppliers', weight: 20)]
class ClaySupplierController extends Controller
{
    public function __construct(private ClaySupplierService $suppliers) {}

    /**
     * Delete a clay supplier
     *
     * Removes the supplier and cascades to its clays.
     */
    public function destroy(ClaySupplier $claySupplier): Response
    {
        $this->authorize('delete', $claySupplier);

        $this->suppliers->delete($claySupplier);

        return response()->noContent();
    }

    /**
     * List clay suppliers
     *
     * Returns a paginated collection of clay suppliers. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ClaySupplier::class);

        return ClaySupplierResource::collection($this->suppliers->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a clay supplier
     *
     * Restores a soft-deleted clay supplier and cascades to its clays. Restricted to staff.
     */
    public function restore(ClaySupplier $claySupplier): JsonResponse
    {
        $this->authorize('restore', $claySupplier);

        return ApiResponse::item(new ClaySupplierResource($this->suppliers->restore($claySupplier)));
    }

    /**
     * Show a clay supplier
     */
    public function show(ClaySupplier $claySupplier): JsonResponse
    {
        $this->authorize('view', $claySupplier);

        return ApiResponse::item(new ClaySupplierResource($claySupplier));
    }

    /**
     * Create a clay supplier
     */
    public function store(StoreClaySupplierRequest $request): JsonResponse
    {
        $this->authorize('create', ClaySupplier::class);

        $supplier = $this->suppliers->create($request->validated());

        return ApiResponse::item(new ClaySupplierResource($supplier), status: Response::HTTP_CREATED);
    }

    /**
     * Update a clay supplier
     */
    public function update(UpdateClaySupplierRequest $request, ClaySupplier $claySupplier): JsonResponse
    {
        $this->authorize('update', $claySupplier);

        $supplier = $this->suppliers->update($request->validated(), $claySupplier);

        return ApiResponse::item(new ClaySupplierResource($supplier));
    }
}
