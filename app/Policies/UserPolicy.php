<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewUsers();
    }

    public function create(User $user): bool
    {
        return $user->canViewUsers();
    }

    public function view(User $user, User $model): bool
    {
        return $user->canViewUsers() || $user->is($model);
    }

    public function update(User $user, User $model): bool
    {
        return $user->canManageUsers() || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->canManageUsers() && ! $user->is($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->canManageUsers();
    }
}
