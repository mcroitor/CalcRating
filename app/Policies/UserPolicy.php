<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function block(User $user, User $target): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Cannot block yourself.
        if ($user->id === $target->id) {
            return false;
        }

        // Cannot block the last active admin.
        if ($target->isAdmin() && User::query()->active()->where('role', 'admin')->count() <= 1) {
            return false;
        }

        return true;
    }

    public function unblock(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
