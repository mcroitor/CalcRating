<?php

namespace App\Services\Pairing;

use App\Enums\PairingResult;
use App\Exceptions\DomainException;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Support\Collection;

class OlympicGenerator
{
    /**
     * Generate the first or next knockout round.
     *
     * @return list<array{white_player_id: int, black_player_id: int|null, result: PairingResult}>
     */
    public function generateNextRound(Tournament $tournament): array
    {
        $participants = $tournament->participants()->with('player')->get();

        if ($participants->count() < 2) {
            throw new DomainException(__('pairings.errors.min_participants'));
        }

        $pairings = $tournament->pairings()->get();

        if ($pairings->isEmpty()) {
            return $this->firstRound($participants);
        }

        $lastRound = (int) $pairings->max('round_number');
        $currentRound = $pairings->where('round_number', $lastRound)->sortBy('board_number')->values();

        foreach ($currentRound as $pairing) {
            if ($pairing->winnerPlayerId() === null) {
                throw new DomainException(
                    __('pairings.errors.finish_round_and_advance')
                );
            }
        }

        if ($lastRound >= $this->roundCount($participants->count())) {
            throw new DomainException(__('pairings.errors.final_round_generated'));
        }

        $winners = $currentRound
            ->map(fn ($pairing) => $pairing->winnerPlayerId())
            ->filter()
            ->values()
            ->all();

        if (count($winners) < 2) {
            throw new DomainException(__('pairings.errors.not_enough_participants_next'));
        }

        $rows = [];

        for ($i = 0; $i + 1 < count($winners); $i += 2) {
            $rows[] = [
                'white_player_id' => $winners[$i],
                'black_player_id' => $winners[$i + 1],
                'result' => PairingResult::Pending,
            ];
        }

        if (count($winners) % 2 === 1) {
            $rows[] = [
                'white_player_id' => $winners[count($winners) - 1],
                'black_player_id' => null,
                'result' => PairingResult::WhiteWin,
            ];
        }

        return $rows;
    }

    /**
     * Seed the first round: strongest vs weakest; top seeds receive byes.
     *
     * @param  Collection<int, TournamentParticipant>  $participants
     * @return list<array{white_player_id: int, black_player_id: int|null, result: PairingResult}>
     */
    private function firstRound(Collection $participants): array
    {
        $sorted = $participants->sort(function (TournamentParticipant $a, TournamentParticipant $b): int {
            $rating = $b->player->current_rating <=> $a->player->current_rating;

            return $rating !== 0 ? $rating : $a->player_id <=> $b->player_id;
        })->values()->all();

        $bracketSize = 1;
        while ($bracketSize < count($sorted)) {
            $bracketSize *= 2;
        }

        $seedOrder = $this->seedOrder($bracketSize);
        $rows = [];

        for ($i = 0; $i < $bracketSize; $i += 2) {
            $first = $seedOrder[$i] <= count($sorted) ? $sorted[$seedOrder[$i] - 1] : null;
            $second = $seedOrder[$i + 1] <= count($sorted) ? $sorted[$seedOrder[$i + 1] - 1] : null;

            if ($first === null && $second === null) {
                continue;
            }

            if ($first === null || $second === null) {
                $player = $first ?? $second;

                $rows[] = [
                    'white_player_id' => $player->player_id,
                    'black_player_id' => null,
                    'result' => PairingResult::WhiteWin,
                ];

                continue;
            }

            $rows[] = [
                'white_player_id' => $first->player_id,
                'black_player_id' => $second->player_id,
                'result' => PairingResult::Pending,
            ];
        }

        return $rows;
    }

    /**
     * Standard bracket seeding, e.g. 8 -> [1, 8, 4, 5, 2, 7, 3, 6].
     *
     * @return list<int>
     */
    private function seedOrder(int $bracketSize): array
    {
        $order = [1];

        for ($size = 2; $size <= $bracketSize; $size *= 2) {
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $size + 1 - $seed;
            }

            $order = $next;
        }

        return $order;
    }

    public function roundCount(int $participantsCount): int
    {
        $rounds = 0;
        $bracketSize = 1;

        while ($bracketSize < $participantsCount) {
            $bracketSize *= 2;
            $rounds++;
        }

        return max(1, $rounds);
    }
}
