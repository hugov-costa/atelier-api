<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Piece\StorePieceRequest;
use App\Http\Requests\Piece\UpdatePieceRequest;
use App\Http\Resources\PieceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Piece;
use App\Services\PieceService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Pieces', weight: 60)]
class PieceController extends Controller
{
    public function __construct(private PieceService $pieces) {}

    /**
     * Delete a piece
     */
    public function destroy(Piece $piece): Response
    {
        $this->authorize('delete', $piece);

        $this->pieces->delete($piece);

        return response()->noContent();
    }

    /**
     * List pieces
     *
     * Returns a paginated collection of pieces. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Piece::class);

        return PieceResource::collection($this->pieces->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a piece
     *
     * Restores a soft-deleted piece. Restricted to staff.
     */
    public function restore(Piece $piece): JsonResponse
    {
        $this->authorize('restore', $piece);

        return ApiResponse::item(new PieceResource($this->pieces->restore($piece)));
    }

    /**
     * Show a piece
     */
    public function show(Piece $piece): JsonResponse
    {
        $this->authorize('view', $piece);

        return ApiResponse::item(
            new PieceResource($piece->load(['clay', 'glaze', 'category', 'user', 'firingCycles']))
        );
    }

    /**
     * Create a piece
     *
     * The price and production cost are computed from the piece kind, clay, glaze, firing
     * cycles and settings, then snapshotted; they are never taken from the request body.
     * Student pieces are charged materials + firing only (no base cost or margin).
     */
    public function store(StorePieceRequest $request): JsonResponse
    {
        $this->authorize('create', Piece::class);

        $piece = $this->pieces->create($request->validated());

        return ApiResponse::item(new PieceResource($piece), status: Response::HTTP_CREATED);
    }

    /**
     * Update a piece
     *
     * Only administrative fields (the name) may be changed. A piece's composition and
     * pricing are snapshotted at creation and never recomputed.
     */
    public function update(UpdatePieceRequest $request, Piece $piece): JsonResponse
    {
        $this->authorize('update', $piece);

        $piece = $this->pieces->update($request->validated(), $piece);

        return ApiResponse::item(new PieceResource($piece));
    }
}
