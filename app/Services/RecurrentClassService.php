<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RecurrentClass;
use App\Models\User;
use App\Support\Pagination;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RecurrentClassService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): RecurrentClass
    {
        return DB::transaction(function () use ($attributes): RecurrentClass {
            $userIds = $this->resolveUserIds($attributes);

            /** @var array<string, mixed> $data */
            $data = Arr::except($attributes, ['user_ids']);

            $class = RecurrentClass::create($data);
            $class->users()->attach($userIds);

            return $class->load('users');
        });
    }

    public function delete(RecurrentClass $class): void
    {
        $class->delete();
    }

    /**
     * @return LengthAwarePaginator<int, RecurrentClass>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return RecurrentClass::query()
            ->with('users')
            ->orderBy('day_of_the_week')
            ->orderBy('start_time')
            ->paginate(Pagination::perPage($perPage));
    }

    public function restore(RecurrentClass $class): RecurrentClass
    {
        $class->restore();

        return $class->load('users');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, RecurrentClass $class): RecurrentClass
    {
        return DB::transaction(function () use ($class, $attributes): RecurrentClass {
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
