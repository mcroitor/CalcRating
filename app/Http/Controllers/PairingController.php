<?php

namespace App\Http\Controllers;

use App\Enums\PairingResult;
use App\Enums\PairingType;
use App\Exceptions\DomainException;
use App\Models\Pairing;
use App\Models\Tournament;
use App\Services\PairingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PairingController extends Controller
{
    public function __construct(
        protected PairingService $pairingService
    ) {}

    /**
     * Generate the round-robin schedule or the next Swiss/Olympic round.
     */
    public function generate(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('managePairings', $tournament);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $rounds = $this->pairingService->generate($tournament, (int) $validated['version']);
        } catch (DomainException $e) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('error', $e->getMessage());
        }

        $message = $tournament->pairing_type === PairingType::RoundRobin
            ? __('pairings.flash.generated_rounds', ['rounds' => $rounds])
            : __('pairings.flash.generated_round');

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', $message);
    }

    /**
     * Save a board result with optimistic locking.
     */
    public function updateResult(Request $request, Tournament $tournament, Pairing $pairing): RedirectResponse
    {
        Gate::authorize('managePairings', $tournament);

        if ($pairing->tournament_id !== $tournament->id) {
            abort(404);
        }

        $validated = $request->validate([
            'result' => ['required', Rule::in([
                PairingResult::WhiteWin->value,
                PairingResult::BlackWin->value,
                PairingResult::Draw->value,
                PairingResult::DoubleForfeit->value,
            ])],
            'advanced_player_id' => ['nullable', 'integer', 'exists:players,id'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->pairingService->saveResult(
                $tournament,
                $pairing,
                PairingResult::from($validated['result']),
                isset($validated['advanced_player_id']) ? (int) $validated['advanced_player_id'] : null,
                (int) $validated['version']
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', __('pairings.flash.result_saved'));
    }
}
