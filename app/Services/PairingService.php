<?php

namespace App\Services;

use App\Enums\PairingResult;
use App\Enums\PairingType;
use App\Exceptions\DomainException;
use App\Models\Pairing;
use App\Models\Tournament;
use App\Services\Pairing\OlympicGenerator;
use App\Services\Pairing\RoundRobinGenerator;
use App\Services\Pairing\SwissGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PairingService
{
    public function __construct(
        protected RoundRobinGenerator $roundRobinGenerator,
        protected SwissGenerator $swissGenerator,
        protected OlympicGenerator $olympicGenerator
    ) {}

    /**
     * Generate the schedule for the tournament.
     *
     * Round-robin creates the complete schedule at once; Swiss and Olympic
     * systems generate exactly one next round.
     *
     * @return int Number of generated rounds.
     *
     * @throws DomainException
     */
    public function generate(Tournament $tournament, int $expectedVersion): int
    {
        return DB::transaction(function () use ($tournament, $expectedVersion) {
            $fresh = Tournament::query()->findOrFail($tournament->getKey());

            $reason = $this->generationBlockReason($fresh);

            if ($reason !== null) {
                throw new DomainException($reason);
            }

            $fresh->updateWithVersion([], $expectedVersion);

            $rounds = match ($fresh->pairing_type) {
                PairingType::RoundRobin => $this->roundRobinGenerator->generate($fresh),
                PairingType::Swiss => [
                    $this->nextRoundNumber($fresh) => $this->swissGenerator->generateNextRound($fresh),
                ],
                PairingType::Olympic => [
                    $this->nextRoundNumber($fresh) => $this->olympicGenerator->generateNextRound($fresh),
                ],
            };

            $rows = [];

            foreach ($rounds as $roundNumber => $games) {
                foreach (array_values($games) as $index => $game) {
                    $rows[] = [
                        'round_number' => (int) $roundNumber,
                        'board_number' => $index + 1,
                        'white_player_id' => $game['white_player_id'],
                        'black_player_id' => $game['black_player_id'],
                        'result' => $game['result']->value,
                        'advanced_player_id' => null,
                        'version' => 1,
                    ];
                }
            }

            $fresh->pairings()->createMany($rows);

            $this->recalculatePoints($fresh);

            return count($rounds);
        });
    }

    /**
     * Save a board result using optimistic locking on the pairing.
     *
     * @throws DomainException
     */
    public function saveResult(
        Tournament $tournament,
        Pairing $pairing,
        PairingResult $result,
        ?int $advancedPlayerId,
        int $expectedVersion
    ): Pairing {
        return DB::transaction(function () use ($tournament, $pairing, $result, $advancedPlayerId, $expectedVersion) {
            $freshTournament = Tournament::query()->findOrFail($tournament->getKey());

            if (! $freshTournament->isInProgress()) {
                throw new DomainException(__('pairings.errors.only_in_progress'));
            }

            $freshPairing = Pairing::query()
                ->where('tournament_id', $freshTournament->id)
                ->findOrFail($pairing->getKey());

            if ($freshPairing->isBye()) {
                throw new DomainException(__('pairings.errors.bye_no_result'));
            }

            if ($result === PairingResult::Pending) {
                throw new DomainException(__('pairings.errors.pending_not_saved'));
            }

            $advanced = null;

            if ($freshTournament->pairing_type === PairingType::Olympic
                && in_array($result, [PairingResult::Draw, PairingResult::DoubleForfeit], true)) {
                if ($advancedPlayerId === null) {
                    throw new DomainException(__('pairings.errors.advanced_required'));
                }

                if (! in_array($advancedPlayerId, [$freshPairing->white_player_id, $freshPairing->black_player_id], true)) {
                    throw new DomainException(__('pairings.errors.advanced_must_be_opponent'));
                }

                $advanced = $advancedPlayerId;
            }

            $freshPairing->updateWithVersion([
                'result' => $result->value,
                'advanced_player_id' => $advanced,
            ], $expectedVersion);

            $this->recalculatePoints($freshTournament);

            return $freshPairing->refresh();
        });
    }

    /**
     * Recalculate points for every participant from all completed pairings.
     */
    public function recalculatePoints(Tournament $tournament): void
    {
        $participants = $tournament->participants()->get();
        $totals = [];

        foreach ($participants as $participant) {
            $totals[$participant->player_id] = 0.0;
        }

        foreach ($tournament->pairings()->get() as $pairing) {
            if ($pairing->result->isPending()) {
                continue;
            }

            if ($pairing->isBye()) {
                $totals[$pairing->white_player_id] = ($totals[$pairing->white_player_id] ?? 0.0) + 1.0;

                continue;
            }

            $totals[$pairing->white_player_id] = ($totals[$pairing->white_player_id] ?? 0.0)
                + $pairing->result->whitePoints();
            $totals[$pairing->black_player_id] = ($totals[$pairing->black_player_id] ?? 0.0)
                + $pairing->result->blackPoints();
        }

        foreach ($participants as $participant) {
            $points = $totals[$participant->player_id] ?? 0.0;

            if (abs((float) $participant->points - $points) < 0.0001) {
                continue;
            }

            $participant->updateWithVersion(['points' => $points], $participant->version);
        }
    }

    /**
     * Whether the next round can be generated right now.
     */
    public function canGenerateNextRound(Tournament $tournament): bool
    {
        return $this->generationBlockReason($tournament) === null;
    }

    /**
     * Human-readable reason why generation is blocked, or null when allowed.
     */
    public function generationBlockReason(Tournament $tournament): ?string
    {
        if (! $tournament->isInProgress()) {
            return __('pairings.errors.generation_only_in_progress');
        }

        if ($tournament->participants()->count() < 2) {
            return __('pairings.errors.min_participants');
        }

        $pairings = $this->pairings($tournament);

        return match ($tournament->pairing_type) {
            PairingType::RoundRobin => $pairings->isEmpty()
                ? null
                : __('pairings.errors.round_robin_generated'),
            PairingType::Swiss => $this->swissBlockReason($tournament, $pairings),
            PairingType::Olympic => $this->olympicBlockReason($tournament, $pairings),
        };
    }

    /**
     * Next round number that generation would create.
     */
    public function nextRoundNumber(Tournament $tournament): int
    {
        if ($tournament->pairing_type === PairingType::RoundRobin) {
            return 1;
        }

        return ((int) $this->pairings($tournament)->max('round_number')) + 1;
    }

    private function swissBlockReason(Tournament $tournament, Collection $pairings): ?string
    {
        if ($pairings->isEmpty()) {
            return null;
        }

        $lastRound = (int) $pairings->max('round_number');
        $currentRound = $pairings->where('round_number', $lastRound);

        if ($currentRound->contains(fn (Pairing $pairing) => $pairing->result->isPending())) {
            return __('pairings.errors.finish_current_round');
        }

        if ($lastRound >= $tournament->rounds_count) {
            return __('pairings.errors.all_rounds_generated');
        }

        return null;
    }

    private function olympicBlockReason(Tournament $tournament, Collection $pairings): ?string
    {
        if ($pairings->isEmpty()) {
            return null;
        }

        $lastRound = (int) $pairings->max('round_number');
        $currentRound = $pairings->where('round_number', $lastRound);

        if ($currentRound->contains(fn (Pairing $pairing) => $pairing->winnerPlayerId() === null)) {
            return __('pairings.errors.finish_round_and_advance');
        }

        if ($lastRound >= $this->olympicGenerator->roundCount($tournament->participants()->count())) {
            return __('pairings.errors.final_round_generated');
        }

        if ($currentRound->filter(fn (Pairing $pairing) => $pairing->winnerPlayerId() !== null)->count() < 2) {
            return __('pairings.errors.not_enough_participants_next');
        }

        return null;
    }

    /**
     * @return Collection<int, Pairing>
     */
    private function pairings(Tournament $tournament): Collection
    {
        return $tournament->relationLoaded('pairings')
            ? $tournament->pairings
            : $tournament->pairings()->get();
    }
}
