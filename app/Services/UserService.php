<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\BusinessDate;
use App\Support\CachedPaginator;
use App\Support\QueryCache;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserService
{
    /**
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name', 'email', 'created_at'];

    public function __construct(
        private SetPasswordService $setPassword,
        private PieceChargeService $charges,
    ) {}

    /**
     * Create a staff-managed account without a password and email a set-password
     * link. The role defaults to `user`; elevated roles are gated by the request.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        $role = isset($attributes['role']) && is_string($attributes['role'])
            ? UserRole::from($attributes['role'])
            : UserRole::User;

        unset($attributes['role']);
        $attributes['admission_date'] ??= BusinessDate::today();

        $user = new User($attributes);
        $user->role = $role;
        $user->password = null;
        $user->save();

        $this->setPassword->sendLink($user);

        return $user;
    }

    /**
     * Bootstrap the first master account with a password. Rejected once any
     * account exists, so it can only run on a fresh installation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createMaster(array $attributes): User
    {
        if (User::query()->withTrashed()->exists()) {
            throw new ConflictHttpException('The application has already been initialised.');
        }

        $password = $attributes['password'] ?? null;
        unset($attributes['password'], $attributes['password_confirmation']);
        $attributes['admission_date'] ??= BusinessDate::today();

        $user = new User($attributes);
        $user->role = UserRole::Master;
        $user->password = is_string($password) ? $password : null;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->normalizePerPage($perPage);
        [$search, $sort, $direction] = $this->normalizeFilters($filters);

        if (! $this->cacheEnabled()) {
            return $this->query($perPage, $search, $sort, $direction);
        }

        $key = sprintf(
            'users:index:pp%d:p%d:q%s:s%s_%s',
            $perPage,
            Paginator::resolveCurrentPage(),
            md5($search),
            $sort,
            $direction,
        );

        $builder = fn (): Builder => $this->builder($search, $sort, $direction);
        $fetch = fn (): LengthAwarePaginator => $this->query($perPage, $search, $sort, $direction);

        $result = $this->rememberSingleFlight($key, $fetch);

        if ($result instanceof LengthAwarePaginator) {
            return $result;
        }

        /** @var LengthAwarePaginator<int, User> */
        return CachedPaginator::fromArray($result, $builder());
    }

    /**
     * Resolve a cached value, serializing concurrent rebuilds behind a lock so a cold
     * or freshly-flushed key triggers a single query instead of a thundering herd.
     *
     * The callback should return a LengthAwarePaginator. It is converted to a cache-safe
     * array (via CachedPaginator) before storage and reconstructed on hit.
     *
     * @param  Closure(): LengthAwarePaginator<int, User>  $callback
     * @return LengthAwarePaginator<int, User>|array{item_ids: array<int, int>, meta: array{total: int, per_page: int, current_page: int, last_page: int, path: string}}
     */
    private function rememberSingleFlight(string $key, Closure $callback): LengthAwarePaginator|array
    {
        $store = QueryCache::tagged([User::cacheTag()]);
        $ttl = $this->cacheTtl();

        $cached = $store->get($key);

        if ($cached !== null) {
            /** @var LengthAwarePaginator<int, User>|array{item_ids: array<int, int>, meta: array{total: int, per_page: int, current_page: int, last_page: int, path: string}} $cached */
            return $cached;
        }

        $lock = Cache::lock('lock:'.$key, 10);

        try {
            $lock->block(5);

            $paginator = $callback();
            $cacheData = CachedPaginator::toArray($paginator);

            if ($ttl === null) {
                $store->forever($key, $cacheData);
            } else {
                $store->put($key, $cacheData, $ttl);
            }

            return $paginator;
        } catch (LockTimeoutException) {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return CursorPaginator<int, User>
     */
    public function cursorPaginate(int $perPage = 15, array $filters = []): CursorPaginator
    {
        $perPage = $this->normalizePerPage($perPage);
        [$search, $sort, $direction] = $this->normalizeFilters($filters);

        return $this->builder($search, $sort, $direction)->cursorPaginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        $newEmail = $attributes['email'] ?? null;
        $emailChanged = is_string($newEmail) && $newEmail !== $user->email;
        $wasActive = $user->is_active;

        $user->fill($attributes);

        if (array_key_exists('role', $attributes) && is_string($attributes['role'])) {
            $user->role = UserRole::from($attributes['role']);
        }

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        if ($wasActive && ! $user->is_active) {
            $this->charges->accelerateForUser($user);
        }

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function restore(User $user): void
    {
        $user->restore();
    }

    /**
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, User>
     */
    private function query(int $perPage, string $search, string $sort, string $direction): LengthAwarePaginator
    {
        return $this->builder($search, $sort, $direction)->paginate($perPage);
    }

    /**
     * @param  'asc'|'desc'  $direction
     * @return Builder<User>
     */
    private function builder(string $search, string $sort, string $direction): Builder
    {
        return User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction);
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return array{0: string, 1: string, 2: 'asc'|'desc'}
     */
    private function normalizeFilters(array $filters): array
    {
        $search = isset($filters['search']) && is_string($filters['search'])
            ? trim($filters['search'])
            : '';

        $sort = isset($filters['sort']) && in_array($filters['sort'], self::SORTABLE_COLUMNS, true)
            ? $filters['sort']
            : 'created_at';

        $direction = isset($filters['direction']) && is_string($filters['direction'])
            && strtolower($filters['direction']) === 'asc'
            ? 'asc'
            : 'desc';

        return [$search, $sort, $direction];
    }

    private function normalizePerPage(int $perPage): int
    {
        return max(1, min(100, $perPage));
    }

    private function cacheEnabled(): bool
    {
        return QueryCache::enabled();
    }

    private function cacheTtl(): ?int
    {
        $ttl = config('cache.query.ttl', 3600);
        $ttl = is_numeric($ttl) ? (int) $ttl : 3600;

        return $ttl > 0 ? $ttl : null;
    }
}
