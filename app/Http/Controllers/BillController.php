<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Bill\StoreBillRequest;
use App\Http\Requests\Bill\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Http\Responses\ApiResponse;
use App\Models\Bill;
use App\Services\BillService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Bills', weight: 30)]
class BillController extends Controller
{
    public function __construct(private BillService $bills) {}

    /**
     * Delete a bill
     */
    public function destroy(Bill $bill): Response
    {
        $this->authorize('delete', $bill);

        $this->bills->delete($bill);

        return response()->noContent();
    }

    /**
     * List bills
     *
     * Returns a paginated collection of expenses, most recent reference period first. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter('reference_year', 'Filter by reference year.', required: false, type: 'integer', example: 2026)]
    #[QueryParameter(
        'reference_month',
        'Filter by reference month (1-12).',
        required: false,
        type: 'integer',
        example: 3,
    )]
    #[QueryParameter('is_recurrent', 'Filter by recurrence.', required: false, type: 'boolean')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Bill::class);

        $filters = [
            'reference_year'  => $request->has('reference_year') ? $request->integer('reference_year') : null,
            'reference_month' => $request->has('reference_month') ? $request->integer('reference_month') : null,
            'is_recurrent'    => $request->has('is_recurrent') ? $request->boolean('is_recurrent') : null,
        ];

        return BillResource::collection($this->bills->paginate($request->integer('per_page', 15), $filters))
            ->additional(['message' => null]);
    }

    /**
     * Restore a bill
     *
     * Restores a soft-deleted bill. Restricted to staff.
     */
    public function restore(Bill $bill): JsonResponse
    {
        $this->authorize('restore', $bill);

        return ApiResponse::item(new BillResource($this->bills->restore($bill)));
    }

    /**
     * Show a bill
     */
    public function show(Bill $bill): JsonResponse
    {
        $this->authorize('view', $bill);

        return ApiResponse::item(new BillResource($bill));
    }

    /**
     * Create a bill
     */
    public function store(StoreBillRequest $request): JsonResponse
    {
        $this->authorize('create', Bill::class);

        $bill = $this->bills->create($request->validated());

        return ApiResponse::item(new BillResource($bill), status: Response::HTTP_CREATED);
    }

    /**
     * Update a bill
     */
    public function update(UpdateBillRequest $request, Bill $bill): JsonResponse
    {
        $this->authorize('update', $bill);

        $bill = $this->bills->update($request->validated(), $bill);

        return ApiResponse::item(new BillResource($bill));
    }
}
