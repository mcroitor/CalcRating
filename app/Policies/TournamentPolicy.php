<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Tournament $tournament): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isArbiter() && $user->hasAnyManagedActiveClub();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tournament $tournament): bool
    {
        return $this->canManageTournament($user, $tournament);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tournament $tournament): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the tournament can be started.
     */
    public function start(User $user, Tournament $tournament): bool
    {
        return $this->canManageTournament($user, $tournament) && $tournament->isDraft();
    }

    /**
     * Determine whether participants can be added or removed.
     */
    public function manageParticipants(User $user, Tournament $tournament): bool
    {
        return $this->canManageTournament($user, $tournament) && $tournament->isDraft();
    }

    /**
     * Determine whether pairings can be generated and results edited.
     */
    public function managePairings(User $user, Tournament $tournament): bool
    {
        return $this->canManageTournament($user, $tournament) && $tournament->isInProgress();
    }

    public function complete(User $user, Tournament $tournament): bool
    {
        return $this->canManageTournament($user, $tournament) && $tournament->isInProgress();
    }

    public function export(User $user, Tournament $tournament): bool
    {
        return $this->canManageTournament($user, $tournament);
    }

    private function canManageTournament(User $user, Tournament $tournament): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isArbiter()) {
            return false;
        }

        if ((int) $tournament->arbiter_id === (int) $user->id) {
            return true;
        }

        return $tournament->club?->hasManagerUser($user) ?? false;
    }
}
