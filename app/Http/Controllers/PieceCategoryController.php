<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PieceCategory\StorePieceCategoryRequest;
use App\Http\Requests\PieceCategory\UpdatePieceCategoryRequest;
use App\Http\Resources\PieceCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\PieceCategory;
use App\Services\PieceCategoryService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Piece categories', weight: 24)]
class PieceCategoryController extends Controller
{
    public function __construct(private PieceCategoryService $categories) {}

    /**
     * Delete a piece category
     */
    public function destroy(PieceCategory $pieceCategory): Response
    {
        $this->authorize('delete', $pieceCategory);

        $this->categories->delete($pieceCategory);

        return response()->noContent();
    }

    /**
     * List piece categories
     *
     * Returns a paginated collection of piece categories. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PieceCategory::class);

        return PieceCategoryResource::collection($this->categories->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a piece category
     *
     * Restores a soft-deleted piece category. Restricted to staff.
     */
    public function restore(PieceCategory $pieceCategory): JsonResponse
    {
        $this->authorize('restore', $pieceCategory);

        return ApiResponse::item(new PieceCategoryResource($this->categories->restore($pieceCategory)));
    }

    /**
     * Show a piece category
     */
    public function show(PieceCategory $pieceCategory): JsonResponse
    {
        $this->authorize('view', $pieceCategory);

        return ApiResponse::item(new PieceCategoryResource($pieceCategory));
    }

    /**
     * Create a piece category
     */
    public function store(StorePieceCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', PieceCategory::class);

        $category = $this->categories->create($request->validated());

        return ApiResponse::item(new PieceCategoryResource($category), status: Response::HTTP_CREATED);
    }

    /**
     * Update a piece category
     */
    public function update(UpdatePieceCategoryRequest $request, PieceCategory $pieceCategory): JsonResponse
    {
        $this->authorize('update', $pieceCategory);

        $category = $this->categories->update($request->validated(), $pieceCategory);

        return ApiResponse::item(new PieceCategoryResource($category));
    }
}
