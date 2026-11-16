<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\Club;
use App\Models\Player;
use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClubMemberController extends Controller
{
    public function __construct(
        private readonly ClubService $clubService,
    ) {}

    public function store(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('manageMembers', $club);

        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:players,id'],
        ]);

        $player = Player::query()->findOrFail((int) $validated['player_id']);

        try {
            $this->clubService->addMember($club, $player, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('clubs.flash.member_added'));
    }

    public function destroy(Request $request, Club $club, Player $player): RedirectResponse
    {
        Gate::authorize('manageMembers', $club);

        try {
            $this->clubService->removeMember($club, $player, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('clubs.flash.member_removed'));
    }
}
