<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PieceCharge\UpdatePieceChargeRequest;
use App\Http\Resources\PieceChargeResource;
use App\Http\Responses\ApiResponse;
use App\Models\PieceCharge;
use App\Services\PieceChargeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Piece charges', weight: 62)]
class PieceChargeController extends Controller
{
    public function __construct(private PieceChargeService $charges) {}

    /**
     * List piece charges
     *
     * Returns a paginated collection of student piece charges. Charges are raised
     * automatically for student pieces and normally settle with their tuition.
     * Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter(
        'status',
        'Filter by payment status: paid or unpaid.',
        required: false,
        type: 'string',
        example: 'unpaid',
    )]
    #[QueryParameter('user_id', 'Filter by the student public id.', required: false, type: 'string')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PieceCharge::class);

        $filters = [
            'status'  => $request->string('status')->toString(),
            'user_id' => $request->string('user_id')->toString(),
        ];

        return PieceChargeResource::collection($this->charges->paginate($request->integer('per_page', 15), $filters))
            ->additional(['message' => null]);
    }

    /**
     * Show a piece charge
     */
    public function show(PieceCharge $pieceCharge): JsonResponse
    {
        $this->authorize('view', $pieceCharge);

        return ApiResponse::item(new PieceChargeResource($pieceCharge->load(['piece', 'user'])));
    }

    /**
     * Mark a piece charge paid or unpaid
     *
     * Manual settlement override. Charges normally settle together with the student's
     * tuition; use this for students with no tuition to bundle against (e.g. tuition-exempt)
     * or to correct a payment. Restricted to staff.
     */
    public function update(UpdatePieceChargeRequest $request, PieceCharge $pieceCharge): JsonResponse
    {
        $this->authorize('update', $pieceCharge);

        $charge = $this->charges->markPaid($pieceCharge, (bool) $request->validated('is_paid'));

        return ApiResponse::item(new PieceChargeResource($charge));
    }
}
