<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Authorization for atelier-management resources: every ability is reserved for
 * staff (admin or master). Students (the default `user` role) have no access to
 * the back-office, so each ability collapses to the same staff check.
 */
trait StaffManaged
{
    public function create(User $user): bool
    {
        return $user->canViewUsers();
    }

    public function delete(User $user): bool
    {
        return $user->canViewUsers();
    }

    public function restore(User $user): bool
    {
        return $user->canViewUsers();
    }

    public function update(User $user): bool
    {
        return $user->canViewUsers();
    }

    public function view(User $user): bool
    {
        return $user->canViewUsers();
    }

    public function viewAny(User $user): bool
    {
        return $user->canViewUsers();
    }
}
