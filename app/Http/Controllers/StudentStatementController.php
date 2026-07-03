<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\StudentStatementService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('Student statements')]
class StudentStatementController extends Controller
{
    public function __construct(private StudentStatementService $statements) {}

    /**
     * Student account statement
     *
     * Returns a consolidated, read-only financial statement for the student: outstanding
     * balance aged into overdue and upcoming, itemised charges, and recent payment history.
     * All monetary values are in cents. Accessible to staff or the student themselves.
     */
    public function show(Request $request, User $student): JsonResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor !== null && ($actor->canViewUsers() || $actor->is($student)),
            Response::HTTP_FORBIDDEN
        );

        return ApiResponse::item($this->statements->build($student));
    }
}
