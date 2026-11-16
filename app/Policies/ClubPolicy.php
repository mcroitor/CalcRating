<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Club $club): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Club $club): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isArbiter() && $club->hasManagerUser($user);
    }

    public function archive(User $user, Club $club): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Club $club): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Club $club): bool
    {
        return $user->isAdmin();
    }

    public function manageManagers(User $user, Club $club): bool
    {
        return $user->isAdmin();
    }

    public function manageArbiters(User $user, Club $club): bool
    {
        if (! $club->isActive()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isArbiter() && $club->hasManagerUser($user);
    }

    public function manageMembers(User $user, Club $club): bool
    {
        if (! $club->isActive()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isArbiter() && $club->hasManagerUser($user);
    }

    public function createTournament(User $user, Club $club): bool
    {
        if (! $club->isActive()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isArbiter() && $club->hasManagerUser($user);
    }
}
