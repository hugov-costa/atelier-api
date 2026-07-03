<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\Audit\AuditFilterRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Models\Audit;

class UserAuditService
{
    /**
     * Paginate audits across every user record.
     *
     * @return LengthAwarePaginator<int, Audit>
     */
    public function paginateAll(AuditFilterRequest $request): LengthAwarePaginator
    {
        return $this->filtered($this->baseQuery(), $request);
    }

    /**
     * Paginate the audit trail of a single user record.
     *
     * @return LengthAwarePaginator<int, Audit>
     */
    public function paginateForUser(User $user, AuditFilterRequest $request): LengthAwarePaginator
    {
        return $this->filtered($this->baseQuery()->where('auditable_id', $user->getKey()), $request);
    }

    /**
     * @return Builder<Audit>
     */
    private function baseQuery(): Builder
    {
        return Audit::query()->where('auditable_type', User::class);
    }

    /**
     * @param  Builder<Audit>  $query
     * @return LengthAwarePaginator<int, Audit>
     */
    private function filtered(Builder $query, AuditFilterRequest $request): LengthAwarePaginator
    {
        $event = $request->validated('event');

        if (is_string($event) && $event !== '') {
            $query->where('event', $event);
        }

        $ip = $request->validated('ip_address');

        if (is_string($ip) && $ip !== '') {
            $query->where('ip_address', $ip);
        }

        if ($from = $request->date('from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        return $query->latest()->paginate($perPage);
    }
}
