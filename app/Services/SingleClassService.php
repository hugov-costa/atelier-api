<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SingleClass;
use App\Models\User;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SingleClassService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SingleClass
    {
        return DB::transaction(function () use ($attributes): SingleClass {
            $userIds = $this->resolveUserIds($attributes);

            /** @var array<string, mixed> $data */
            $data = Arr::except($attributes, ['user_ids']);

            $class = SingleClass::create($data);
            $class->users()->attach($userIds);

            return $class->load('users');
        });
    }

    public function delete(SingleClass $class): void
    {
        $class->delete();
    }

    /**
     * @return LengthAwarePaginator<int, SingleClass>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return SingleClass::query()
            ->with('users')
            ->orderByDesc('start_datetime')
            ->paginate(Pagination::perPage($perPage));
    }

    public function restore(SingleClass $class): SingleClass
    {
        $class->restore();

        return $class->load('users');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, SingleClass $class): SingleClass
    {
        return DB::transaction(function () use ($class, $attributes): SingleClass {
            $userIds = array_key_exists('user_ids', $attributes) ? $this->resolveUserIds($attributes) : null;

            /** @var array<string, mixed> $data */
            $data = Arr::except($attributes, ['user_ids']);

            $class->update($data);

            if ($userIds !== null) {
                $class->users()->sync($userIds);
            }

            return $class->load('users');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, int>
     */
    private function resolveUserIds(array $attributes): array
    {
        $ulids = $attributes['user_ids'] ?? [];

        if (! is_array($ulids)) {
            return [];
        }

        return PublicId::resolveMany(User::class, array_values(array_filter($ulids, 'is_string')));
    }
}
