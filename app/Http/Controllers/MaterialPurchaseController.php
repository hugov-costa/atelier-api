<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MaterialPurchase\StoreMaterialPurchaseRequest;
use App\Http\Requests\MaterialPurchase\UpdateMaterialPurchaseRequest;
use App\Http\Resources\MaterialPurchaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\MaterialPurchase;
use App\Services\MaterialPurchaseService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Material purchases', weight: 22)]
class MaterialPurchaseController extends Controller
{
    public function __construct(private MaterialPurchaseService $purchases) {}

    /**
     * Delete a material purchase
     */
    public function destroy(MaterialPurchase $materialPurchase): Response
    {
        $this->authorize('delete', $materialPurchase);

        $this->purchases->delete($materialPurchase);

        return response()->noContent();
    }

    /**
     * List material purchases
     *
     * Returns a paginated collection of material purchases, most recent purchase date first. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter(
        'status',
        'Filter by receipt status (received|pending).',
        required: false,
        type: 'string',
        example: 'received',
    )]
    #[QueryParameter(
        'material_type',
        'Filter by material kind (clay|glaze).',
        required: false,
        type: 'string',
        example: 'clay',
    )]
    #[QueryParameter(
        'supplier_id',
        'Filter by supplier public id (ULID). Requires material_type.',
        required: false,
        type: 'string',
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MaterialPurchase::class);

        $filters = [
            'status'        => $request->has('status') ? $request->string('status')->toString() : null,
            'material_type' => $request->has('material_type')
                ? $request->string('material_type')->toString()
                : null,
            'supplier_id' => $request->has('supplier_id') ? $request->string('supplier_id')->toString() : null,
        ];

        return MaterialPurchaseResource::collection(
            $this->purchases->paginate($request->integer('per_page', 15), $filters)
        )->additional(['message' => null]);
    }

    /**
     * Restore a material purchase
     *
     * Restores a soft-deleted material purchase. Restricted to staff.
     */
    public function restore(MaterialPurchase $materialPurchase): JsonResponse
    {
        $this->authorize('restore', $materialPurchase);

        return ApiResponse::item(new MaterialPurchaseResource($this->purchases->restore($materialPurchase)));
    }

    /**
     * Show a material purchase
     */
    public function show(MaterialPurchase $materialPurchase): JsonResponse
    {
        $this->authorize('view', $materialPurchase);

        return ApiResponse::item(new MaterialPurchaseResource($materialPurchase->load(['material', 'supplier'])));
    }

    /**
     * Create a material purchase
     */
    public function store(StoreMaterialPurchaseRequest $request): JsonResponse
    {
        $this->authorize('create', MaterialPurchase::class);

        $purchase = $this->purchases->create($request->validated());

        return ApiResponse::item(new MaterialPurchaseResource($purchase), status: Response::HTTP_CREATED);
    }

    /**
     * Update a material purchase
     */
    public function update(UpdateMaterialPurchaseRequest $request, MaterialPurchase $materialPurchase): JsonResponse
    {
        $this->authorize('update', $materialPurchase);

        $purchase = $this->purchases->update($request->validated(), $materialPurchase);

        return ApiResponse::item(new MaterialPurchaseResource($purchase));
    }
}
