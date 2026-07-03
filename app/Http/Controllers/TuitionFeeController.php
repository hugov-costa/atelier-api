<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TuitionFee\StoreTuitionFeeRequest;
use App\Http\Requests\TuitionFee\UpdateTuitionFeeRequest;
use App\Http\Resources\TuitionFeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\TuitionFee;
use App\Services\TuitionFeeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Tuition fees', weight: 51)]
class TuitionFeeController extends Controller
{
    public function __construct(private TuitionFeeService $tuitionFees) {}

    /**
     * Delete a tuition fee
     */
    public function destroy(TuitionFee $tuitionFee): Response
    {
        $this->authorize('delete', $tuitionFee);

        $this->tuitionFees->delete($tuitionFee);

        return response()->noContent();
    }

    /**
     * List tuition fees
     *
     * Returns a paginated collection of monthly tuition charges. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter(
        'status',
        'Filter by payment status: "paid" or "unpaid".',
        required: false,
        type: 'string',
        example: 'unpaid',
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TuitionFee::class);

        $filters = ['status' => $request->string('status')->toString()];

        return TuitionFeeResource::collection($this->tuitionFees->paginate($request->integer('per_page', 15), $filters))
            ->additional(['message' => null]);
    }

    /**
     * Restore a tuition fee
     *
     * Restores a soft-deleted tuition fee. Restricted to staff.
     */
    public function restore(TuitionFee $tuitionFee): JsonResponse
    {
        $this->authorize('restore', $tuitionFee);

        return ApiResponse::item(new TuitionFeeResource($this->tuitionFees->restore($tuitionFee)));
    }

    /**
     * Show a tuition fee
     */
    public function show(TuitionFee $tuitionFee): JsonResponse
    {
        $this->authorize('view', $tuitionFee);

        return ApiResponse::item(new TuitionFeeResource($tuitionFee->load('enrollment')));
    }

    /**
     * Create a tuition fee
     *
     * The due date is derived from the settings; it is marked paid immediately when the
     * enrollment is exempt from tuition.
     */
    public function store(StoreTuitionFeeRequest $request): JsonResponse
    {
        $this->authorize('create', TuitionFee::class);

        $tuitionFee = $this->tuitionFees->create($request->validated());

        return ApiResponse::item(new TuitionFeeResource($tuitionFee), status: Response::HTTP_CREATED);
    }

    /**
     * Update a tuition fee
     *
     * Marks the tuition paid or unpaid.
     */
    public function update(UpdateTuitionFeeRequest $request, TuitionFee $tuitionFee): JsonResponse
    {
        $this->authorize('update', $tuitionFee);

        $tuitionFee = $this->tuitionFees->update($request->validated(), $tuitionFee);

        return ApiResponse::item(new TuitionFeeResource($tuitionFee));
    }
}
