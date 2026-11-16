<?php

namespace App\Http\Controllers;

use App\Enums\ClubStaffRole;
use App\Exceptions\DomainException;
use App\Models\Club;
use App\Models\User;
use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;

class ClubStaffController extends Controller
{
    public function __construct(
        private readonly ClubService $clubService,
    ) {}

    public function store(Request $request, Club $club): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', new Enum(ClubStaffRole::class)],
        ]);

        $role = ClubStaffRole::from($validated['role']);

        if ($role === ClubStaffRole::Manager) {
            Gate::authorize('manageManagers', $club);
        } else {
            Gate::authorize('manageArbiters', $club);
        }

        $targetUser = User::query()->findOrFail((int) $validated['user_id']);

        $existingPivot = $club->staff()->where('users.id', $targetUser->id)->first();
        if ($existingPivot !== null && $existingPivot->pivot->role === ClubStaffRole::Manager->value) {
            Gate::authorize('manageManagers', $club);
        }

        try {
            $this->clubService->assignStaff($club, $targetUser, $role, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('clubs.flash.staff_updated'));
    }

    public function destroy(Request $request, Club $club, User $user): RedirectResponse
    {
        $existingPivot = $club->staff()->where('users.id', $user->id)->first();

        if ($existingPivot !== null && $existingPivot->pivot->role === ClubStaffRole::Manager->value) {
            Gate::authorize('manageManagers', $club);
        } else {
            Gate::authorize('manageArbiters', $club);
        }

        try {
            $this->clubService->removeStaff($club, $user, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('clubs.flash.staff_removed'));
    }
}
