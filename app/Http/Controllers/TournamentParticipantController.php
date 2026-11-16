<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Services\TournamentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TournamentParticipantController extends Controller
{
    public function __construct(
        protected TournamentService $tournamentService
    ) {}

    /**
     * Add a player to the tournament roster.
     */
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('manageParticipants', $tournament);

        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:players,id'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->tournamentService->addParticipant(
                $tournament,
                (int) $validated['player_id'],
                (int) $validated['version']
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', __('tournaments.flash.participant_added'));
    }

    /**
     * Remove a player from the tournament roster.
     */
    public function destroy(
        Request $request,
        Tournament $tournament,
        TournamentParticipant $participant
    ): RedirectResponse {
        Gate::authorize('manageParticipants', $tournament);

        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->tournamentService->removeParticipant(
                $tournament,
                $participant,
                (int) $validated['version']
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', __('tournaments.flash.participant_removed'));
    }
}
