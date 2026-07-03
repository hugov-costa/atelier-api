<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Enrollments', weight: 50)]
class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollments) {}

    /**
     * Delete an enrollment
     */
    public function destroy(Enrollment $enrollment): Response
    {
        $this->authorize('delete', $enrollment);

        $this->enrollments->delete($enrollment);

        return response()->noContent();
    }

    /**
     * List enrollments
     *
     * Returns a paginated collection of student enrollments. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter(
        'annual_fee_is_paid',
        'Filter by whether the annual fee is paid.',
        required: false,
        type: 'boolean',
    )]
    #[QueryParameter('user_id', 'Filter by the student public id (ULID).', required: false, type: 'string')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Enrollment::class);

        $filters = [
            'annual_fee_is_paid' => $request->has('annual_fee_is_paid')
                ? $request->boolean('annual_fee_is_paid')
                : null,
            'user_id' => $request->string('user_id')->toString(),
        ];

        return EnrollmentResource::collection($this->enrollments->paginate($request->integer('per_page', 15), $filters))
            ->additional(['message' => null]);
    }

    /**
     * Restore an enrollment
     *
     * Restores a soft-deleted enrollment and cascades to its tuition fees. Restricted to staff.
     */
    public function restore(Enrollment $enrollment): JsonResponse
    {
        $this->authorize('restore', $enrollment);

        return ApiResponse::item(new EnrollmentResource($this->enrollments->restore($enrollment)));
    }

    /**
     * Show an enrollment
     */
    public function show(Enrollment $enrollment): JsonResponse
    {
        $this->authorize('view', $enrollment);

        return ApiResponse::item(new EnrollmentResource($enrollment->load('user')));
    }

    /**
     * Create an enrollment
     *
     * The annual fee is derived from the current settings (zero when the student is exempt).
     */
    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $this->authorize('create', Enrollment::class);

        $enrollment = $this->enrollments->create($request->validated());

        return ApiResponse::item(new EnrollmentResource($enrollment), status: Response::HTTP_CREATED);
    }

    /**
     * Update an enrollment
     */
    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment): JsonResponse
    {
        $this->authorize('update', $enrollment);

        $enrollment = $this->enrollments->update($request->validated(), $enrollment);

        return ApiResponse::item(new EnrollmentResource($enrollment));
    }
}
