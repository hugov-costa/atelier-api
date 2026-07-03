<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Audit\AuditFilterRequest;
use App\Http\Resources\AuditResource;
use App\Models\User;
use App\Services\UserAuditService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Audits', weight: 7)]
class UserAuditController extends Controller
{
    public function __construct(private UserAuditService $audits) {}

    /**
     * List all user audits
     *
     * Returns a paginated, filterable audit trail across every user. Restricted to administrators.
     */
    public function index(AuditFilterRequest $request): AnonymousResourceCollection
    {
        return AuditResource::collection($this->audits->paginateAll($request))->additional(['message' => null]);
    }

    /**
     * List a single user's audits
     *
     * Returns the paginated, filterable audit trail for one user. Restricted to administrators.
     */
    public function byUser(AuditFilterRequest $request, User $user): AnonymousResourceCollection
    {
        return AuditResource::collection($this->audits->paginateForUser($user, $request))
            ->additional(['message' => null]);
    }
}
