<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\User\CreateMasterRequest;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AccountService;
use App\Services\UserService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Users', weight: 3)]
class UserController extends Controller
{
    public function __construct(
        private UserService $users,
        private AccountService $accounts,
    ) {}

    /**
     * List users
     *
     * Returns a paginated collection of users. Restricted to administrators. Supports
     * filtering by name or email, sorting, and either length-aware (default) or cursor
     * pagination.
     */
    #[QueryParameter('per_page', 'Number of users per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter('search', 'Filters users whose name or email contains the term.', required: false, type: 'string')]
    #[QueryParameter(
        'sort',
        'Column to sort by: name, email or created_at.',
        required: false,
        type: 'string',
        example: 'created_at',
    )]
    #[QueryParameter('direction', 'Sort direction: asc or desc.', required: false, type: 'string', example: 'desc')]
    #[QueryParameter(
        'paginator',
        'Pagination strategy: "length-aware" (default) or "cursor".',
        required: false,
        type: 'string',
        example: 'cursor',
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $filters = [
            'search'    => $request->string('search')->toString(),
            'sort'      => $request->string('sort')->toString(),
            'direction' => $request->string('direction')->toString(),
        ];

        $perPage = $request->integer('per_page', 15);

        $paginator = $request->string('paginator')->toString() === 'cursor'
            ? $this->users->cursorPaginate($perPage, $filters)
            : $this->users->paginate($perPage, $filters);

        return UserResource::collection($paginator)->additional(['message' => null]);
    }

    /**
     * Create a user
     *
     * Creates a staff-managed account without a password and emails a set-password link.
     * Restricted to staff; only a master may assign an elevated role.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return ApiResponse::item(new UserResource($user), status: Response::HTTP_CREATED);
    }

    /**
     * Bootstrap the first master
     *
     * One-time creation of the initial master account with a password. Rejected once any
     * account exists.
     *
     * @unauthenticated
     */
    public function storeMaster(CreateMasterRequest $request): JsonResponse
    {
        $user = $this->users->createMaster($request->validated());

        return ApiResponse::item(new UserResource($user), status: Response::HTTP_CREATED);
    }

    /**
     * Show a user
     *
     * Returns a single user. Accessible to the user themselves or to administrators.
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::item(new UserResource($user));
    }

    /**
     * Update a user
     *
     * Updates the user's name and/or email. Accessible to the user themselves or to administrators.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return ApiResponse::item(new UserResource($this->users->update($user, $request->validated())));
    }

    /**
     * Delete a user
     *
     * Soft-deletes the user (recoverable). Restricted to masters and cannot target the acting
     * account. Use this for operational removal; the account stays restorable.
     */
    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return response()->noContent();
    }

    /**
     * Erase a user
     *
     * Irreversibly erases the user's personal data (anonymises identifying fields, removes the
     * avatar and revokes tokens) and soft-deletes the account. Restricted to masters and cannot
     * target the acting account. Use this to fulfil a data subject's LGPD/GDPR erasure request.
     */
    public function erase(User $user): Response
    {
        $this->authorize('delete', $user);

        $this->accounts->erase($user);

        return response()->noContent();
    }

    /**
     * Restore a user
     *
     * Restores a soft-deleted user. Restricted to administrators.
     */
    public function restore(User $user): JsonResponse
    {
        $this->authorize('restore', $user);

        $this->users->restore($user);

        return ApiResponse::item(new UserResource($user));
    }
}
