<?php

namespace App\Actions;

use App\Enums\PairingResult;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainException;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Rating;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EloCalculationService;
use App\Support\RatingResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompleteTournamentAction
{
    public function __construct(
        protected EloCalculationService $elo,
        protected AuditLogger $audit
    ) {}

    /**
     * Close the tournament, persist the Elo results and freeze rating history.
     *
     * @return Collection<int, RatingResult>
     *
     * @throws DomainException
     */
    public function execute(
        Tournament $tournament,
        int $expectedVersion,
        User $actor,
        bool $overlapConfirmed = false
    ): Collection {
        return DB::transaction(function () use ($tournament, $expectedVersion, $actor, $overlapConfirmed) {
            $fresh = Tournament::query()->findOrFail($tournament->getKey());
            $pairings = $fresh->pairings()->get();

            $this->assertCompletable($fresh, $pairings);

            $participants = $fresh->participants()->with('player')->get();
            $overlaps = $this->overlaps($participants);
            $overlapData = $overlaps->map(fn (TournamentParticipant $participant) => [
                'player_id' => $participant->player_id,
                'current_rating' => $participant->player->current_rating,
                'rating_before' => $participant->rating_before,
            ])->values()->all();

            if ($overlaps->isNotEmpty() && ! $overlapConfirmed) {
                throw new DomainException($this->overlapMessage($overlaps));
            }

            // Compare-and-swap the tournament first: a concurrent completion
            // aborts before any participant, player or rating row is touched.
            $fresh->updateWithVersion(
                ['status' => TournamentStatus::Completed->value],
                $expectedVersion
            );

            $results = $this->elo->calculateForTournament($fresh);

            foreach ($results as $result) {
                $this->persist($result, $participants);
            }

            $this->audit->log('tournament.completed', $actor, 'tournament', $fresh->id, [
                'tournament_id' => $fresh->id,
                'rating_mode' => $fresh->rating_mode->value,
                'participants' => $participants->count(),
                'overlapping_players' => $overlapData,
                'ratings' => $results->map(fn (RatingResult $result) => [
                    'player_id' => $result->playerId,
                    'rating_before' => $result->ratingBefore,
                    'rating_after' => $result->ratingAfter,
                    'games' => $result->games,
                    'k_factor' => $result->kFactor,
                ])->values()->all(),
            ]);

            foreach ($overlaps as $participant) {
                $result = $results[$participant->id] ?? null;

                $this->audit->log('rating.changed', $actor, 'player', $participant->player_id, [
                    'tournament_id' => $fresh->id,
                    'player_id' => $participant->player_id,
                    'current_rating' => $participant->player->current_rating,
                    'rating_before' => $participant->rating_before,
                    'rating_after' => $result?->ratingAfter,
                ]);
            }

            return $results;
        });
    }

    /**
     * Reason why the tournament cannot be completed yet, or null when ready.
     */
    public function completionBlockReason(Tournament $tournament): ?string
    {
        if (! $tournament->isInProgress()) {
            return __('tournaments.errors.complete_only_in_progress');
        }

        if (! $tournament->pairings()->exists()) {
            return __('tournaments.errors.complete_need_schedule');
        }

        if ($tournament->pairings()
            ->where('result', PairingResult::Pending->value)
            ->exists()) {
            return __('tournaments.errors.complete_pending_games');
        }

        return null;
    }

    /**
     * Participants whose current rating differs from the frozen starting rating
     * because another tournament finished in the meantime.
     *
     * @return EloquentCollection<int, TournamentParticipant>
     */
    public function overlappingParticipants(Tournament $tournament): EloquentCollection
    {
        return $this->overlaps(
            $tournament->participants()->with('player')->get()
        );
    }

    /**
     * @param  EloquentCollection<int, TournamentParticipant>  $participants
     * @return EloquentCollection<int, TournamentParticipant>
     */
    private function overlaps(EloquentCollection $participants): EloquentCollection
    {
        return $participants
            ->filter(fn (TournamentParticipant $participant) => $participant->player instanceof Player
                && $participant->player->current_rating !== $participant->rating_before)
            ->values();
    }

    /**
     * @param  EloquentCollection<int, TournamentParticipant>  $overlaps
     */
    private function overlapMessage(EloquentCollection $overlaps): string
    {
        $names = $overlaps
            ->map(fn (TournamentParticipant $participant) => $participant->player->full_name)
            ->implode(', ');

        return __('tournaments.errors.rating_overlap', ['names' => $names]);
    }

    /**
     * @param  EloquentCollection<int, TournamentParticipant>  $participants
     */
    private function persist(RatingResult $result, EloquentCollection $participants): void
    {
        $participant = $participants->firstWhere('id', $result->participantId);

        if ($participant === null) {
            throw new DomainException(__('tournaments.errors.participant_not_found'));
        }

        $participant->updateWithVersion(
            ['rating_after' => $result->ratingAfter],
            $participant->version
        );

        $participant->player->updateWithVersion(
            ['current_rating' => $result->ratingAfter],
            $participant->player->version
        );

        Rating::query()->create([
            'player_id' => $result->playerId,
            'tournament_id' => $participant->tournament_id,
            'value' => $result->ratingAfter,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, Pairing>  $pairings
     *
     * @throws DomainException
     */
    private function assertCompletable(Tournament $tournament, Collection $pairings): void
    {
        if (! $tournament->isInProgress()) {
            throw new DomainException(__('tournaments.errors.complete_only_in_progress'));
        }

        if ($pairings->isEmpty()) {
            throw new DomainException(__('tournaments.errors.complete_need_schedule'));
        }

        if ($pairings->contains(fn ($pairing) => $pairing->result->isPending())) {
            throw new DomainException(__('tournaments.errors.complete_pending_games'));
        }
    }
}
