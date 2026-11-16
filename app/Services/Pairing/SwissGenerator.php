<?php

namespace App\Services\Pairing;

use App\Enums\PairingResult;
use App\Exceptions\DomainException;
use App\Models\Pairing;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Support\Collection;

class SwissGenerator
{
    /**
     * Generate the next Swiss round.
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
        $lastRound = (int) $pairings->max('round_number');
        $nextRound = $lastRound + 1;

        if ($nextRound > $tournament->rounds_count) {
            throw new DomainException(__('pairings.errors.all_rounds_generated'));
        }

        if ($pairings->isNotEmpty() && $pairings->where('round_number', $lastRound)->contains(
            fn ($pairing) => $pairing->result->isPending()
        )) {
            throw new DomainException(__('pairings.errors.finish_current_round'));
        }

        $sorted = $participants->sort(function (TournamentParticipant $a, TournamentParticipant $b): int {
            $points = (float) $b->points <=> (float) $a->points;

            if ($points !== 0) {
                return $points;
            }

            $rating = $b->player->current_rating <=> $a->player->current_rating;

            return $rating !== 0 ? $rating : $a->player_id <=> $b->player_id;
        })->values()->all();

        $history = $this->pairHistory($pairings);
        $byeRows = [];
        $usedByes = $pairings
            ->whereNull('black_player_id')
            ->pluck('white_player_id')
            ->all();

        if (count($sorted) % 2 === 1) {
            $byePlayer = null;

            for ($i = count($sorted) - 1; $i >= 0; $i--) {
                if (! in_array($sorted[$i]->player_id, $usedByes, true)) {
                    $byePlayer = $sorted[$i];
                    break;
                }
            }

            $byePlayer ??= $sorted[count($sorted) - 1];

            $byeRows[] = [
                'white_player_id' => $byePlayer->player_id,
                'black_player_id' => null,
                'result' => PairingResult::WhiteWin,
            ];

            $sorted = array_values(array_filter(
                $sorted,
                fn (TournamentParticipant $participant) => $participant->player_id !== $byePlayer->player_id
            ));
        }

        $rows = [];

        while (count($sorted) >= 2) {
            /** @var TournamentParticipant $white */
            $white = array_shift($sorted);
            $opponentIndex = null;

            foreach ($sorted as $index => $candidate) {
                if (! $this->hasPlayed($history, $white->player_id, $candidate->player_id)) {
                    $opponentIndex = $index;
                    break;
                }
            }

            // Fallback: avoid a dead end when every remaining opponent was already played.
            $opponentIndex ??= 0;
            $black = $sorted[$opponentIndex];
            array_splice($sorted, $opponentIndex, 1);

            $rows[] = [
                'white_player_id' => $white->player_id,
                'black_player_id' => $black->player_id,
                'result' => PairingResult::Pending,
            ];
        }

        return array_merge($byeRows, $rows);
    }

    /**
     * @param  Collection<int, Pairing>  $pairings
     * @return array<string, true>
     */
    private function pairHistory(Collection $pairings): array
    {
        $history = [];

        foreach ($pairings as $pairing) {
            if ($pairing->black_player_id === null) {
                continue;
            }

            $history[$this->pairKey($pairing->white_player_id, $pairing->black_player_id)] = true;
        }

        return $history;
    }

    /**
     * @param  array<string, true>  $history
     */
    private function hasPlayed(array $history, int $first, int $second): bool
    {
        return isset($history[$this->pairKey($first, $second)]);
    }

    private function pairKey(int $first, int $second): string
    {
        return $first < $second ? "{$first}-{$second}" : "{$second}-{$first}";
    }
}
