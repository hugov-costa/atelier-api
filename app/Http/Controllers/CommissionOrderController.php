<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CommissionOrder\StoreCommissionOrderRequest;
use App\Http\Requests\CommissionOrder\UpdateCommissionOrderRequest;
use App\Http\Resources\CommissionOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\CommissionOrder;
use App\Services\CommissionOrderService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Commission orders', weight: 61)]
class CommissionOrderController extends Controller
{
    public function __construct(private CommissionOrderService $orders) {}

    /**
     * Delete a commission order
     */
    public function destroy(CommissionOrder $commissionOrder): Response
    {
        $this->authorize('delete', $commissionOrder);

        $this->orders->delete($commissionOrder);

        return response()->noContent();
    }

    /**
     * List commission orders
     *
     * Returns a paginated collection of commission orders, most recent order date first. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter(
        'status',
        'Filter by status (pending|in_production|ready|delivered).',
        required: false,
        type: 'string',
        example: 'pending',
    )]
    #[QueryParameter('customer_id', 'Filter by customer public id (ULID).', required: false, type: 'string')]
    #[QueryParameter(
        'paid',
        'Filter by payment status (paid|unpaid).',
        required: false,
        type: 'string',
        example: 'unpaid',
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CommissionOrder::class);

        $filters = [
            'status'      => $request->has('status') ? $request->string('status')->toString() : null,
            'customer_id' => $request->has('customer_id') ? $request->string('customer_id')->toString() : null,
            'paid'        => $request->has('paid') ? $request->string('paid')->toString() : null,
        ];

        return CommissionOrderResource::collection($this->orders->paginate($request->integer('per_page', 15), $filters))
            ->additional(['message' => null]);
    }

    /**
     * Restore a commission order
     *
     * Restores a soft-deleted commission order. Restricted to staff.
     */
    public function restore(CommissionOrder $commissionOrder): JsonResponse
    {
        $this->authorize('restore', $commissionOrder);

        return ApiResponse::item(new CommissionOrderResource($this->orders->restore($commissionOrder)));
    }

    /**
     * Show a commission order
     */
    public function show(CommissionOrder $commissionOrder): JsonResponse
    {
        $this->authorize('view', $commissionOrder);

        return ApiResponse::item(new CommissionOrderResource($commissionOrder->load(['customer', 'pieces'])));
    }

    /**
     * Create a commission order
     */
    public function store(StoreCommissionOrderRequest $request): JsonResponse
    {
        $this->authorize('create', CommissionOrder::class);

        $order = $this->orders->create($request->validated());

        return ApiResponse::item(new CommissionOrderResource($order), status: Response::HTTP_CREATED);
    }

    /**
     * Update a commission order
     */
    public function update(UpdateCommissionOrderRequest $request, CommissionOrder $commissionOrder): JsonResponse
    {
        $this->authorize('update', $commissionOrder);

        $order = $this->orders->update($request->validated(), $commissionOrder);

        return ApiResponse::item(new CommissionOrderResource($order));
    }
}
