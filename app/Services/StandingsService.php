<?php

namespace App\Services;

use App\Enums\PairingResult;
use App\Enums\PairingType;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Support\StandingRow;
use Illuminate\Support\Collection;

class StandingsService
{
    /**
     * Build the deterministic tournament standings.
     *
     * Wins, head-to-head scores and Olympic elimination stages are derived
     * from the saved pairings.
     *
     * @return Collection<int, StandingRow>
     */
    public function build(Tournament $tournament): Collection
    {
        $participants = $tournament->participants()
            ->with('player')
            ->get();

        $pairings = $tournament->pairings()->get();
        $isOlympic = $tournament->pairing_type === PairingType::Olympic;

        $wins = [];
        $headToHead = [];
        $eliminated = [];

        foreach ($pairings as $pairing) {
            if ($pairing->result->isPending()) {
                continue;
            }

            $whiteId = $pairing->white_player_id;
            $blackId = $pairing->black_player_id;

            if ($blackId === null) {
                if ($pairing->result === PairingResult::WhiteWin) {
                    $wins[$whiteId] = ($wins[$whiteId] ?? 0) + 1;
                }

                continue;
            }

            $headToHead[$whiteId][$blackId] = ($headToHead[$whiteId][$blackId] ?? 0.0)
                + $pairing->result->whitePoints();
            $headToHead[$blackId][$whiteId] = ($headToHead[$blackId][$whiteId] ?? 0.0)
                + $pairing->result->blackPoints();

            $winnerId = $pairing->winnerPlayerId();

            if ($winnerId === null) {
                continue;
            }

            if (in_array($pairing->result, [PairingResult::WhiteWin, PairingResult::BlackWin], true)) {
                $wins[$winnerId] = ($wins[$winnerId] ?? 0) + 1;
            }

            if ($isOlympic) {
                $loserId = $winnerId === $whiteId ? $blackId : $whiteId;
                $eliminated[$loserId] = max($eliminated[$loserId] ?? 0, $pairing->round_number);
            }
        }

        $rows = $participants->map(fn (TournamentParticipant $participant) => new StandingRow(
            participantId: $participant->id,
            playerId: $participant->player_id,
            playerName: $participant->player->full_name,
            points: (float) $participant->points,
            wins: $wins[$participant->player_id] ?? 0,
            headToHead: $headToHead[$participant->player_id] ?? [],
            ratingBefore: $participant->rating_before,
            ratingAfter: $participant->rating_after,
            eliminatedRound: $isOlympic ? ($eliminated[$participant->player_id] ?? null) : null,
            playerTitle: $participant->player->title,
        ));

        return $this->sort($rows, $tournament->pairing_type);
    }

    /**
     * Sort standings using the agreed tiebreak chain:
     * points ↓ → head-to-head (exactly two tied participants) → wins ↓ →
     * rating_before ↓ → player ID ↑.
     *
     * @param  Collection<int, StandingRow>  $rows
     * @return Collection<int, StandingRow>
     */
    public function sort(Collection $rows, PairingType $pairingType = PairingType::Swiss): Collection
    {
        $rows = $rows->values();

        if ($pairingType === PairingType::Olympic) {
            return $this->sortOlympic($rows);
        }

        return $this->sortByPoints($rows);
    }

    /**
     * @param  Collection<int, StandingRow>  $rows
     * @return Collection<int, StandingRow>
     */
    private function sortByPoints(Collection $rows): Collection
    {
        $result = collect();
        $remaining = $rows->sortByDesc('points')->values();
        $index = 0;
        $count = $remaining->count();

        while ($index < $count) {
            $points = $remaining[$index]->points;
            $group = [];

            while ($index < $count && abs($remaining[$index]->points - $points) < 0.0001) {
                $group[] = $remaining[$index];
                $index++;
            }

            foreach ($this->sortGroup($group) as $row) {
                $result->push($row);
            }
        }

        return $result;
    }

    /**
     * For the Olympic system placement is defined by the elimination stage;
     * points sort participants only inside the same stage.
     *
     * @param  Collection<int, StandingRow>  $rows
     * @return Collection<int, StandingRow>
     */
    private function sortOlympic(Collection $rows): Collection
    {
        $result = collect();
        $remaining = $rows->sort(fn (StandingRow $a, StandingRow $b) => $this->compareEliminationStage($a, $b))->values();
        $index = 0;
        $count = $remaining->count();

        while ($index < $count) {
            $stage = $remaining[$index]->eliminatedRound;
            $stageGroup = [];

            while ($index < $count && $remaining[$index]->eliminatedRound === $stage) {
                $stageGroup[] = $remaining[$index];
                $index++;
            }

            foreach ($this->sortByPoints(collect($stageGroup)) as $row) {
                $result->push($row);
            }
        }

        return $result;
    }

    /**
     * @param  list<StandingRow>  $group
     * @return list<StandingRow>
     */
    private function sortGroup(array $group): array
    {
        $applyHeadToHead = count($group) === 2;

        usort($group, function (StandingRow $a, StandingRow $b) use ($applyHeadToHead): int {
            if ($applyHeadToHead) {
                $aScore = $a->headToHeadScoreAgainst($b->playerId);
                $bScore = $b->headToHeadScoreAgainst($a->playerId);

                if ($aScore !== $bScore) {
                    return $aScore > $bScore ? -1 : 1;
                }
            }

            return $this->compareTiebreak($a, $b);
        });

        return $group;
    }

    private function compareTiebreak(StandingRow $a, StandingRow $b): int
    {
        if ($a->wins !== $b->wins) {
            return $b->wins <=> $a->wins;
        }

        if ($a->ratingBefore !== $b->ratingBefore) {
            return $b->ratingBefore <=> $a->ratingBefore;
        }

        return $a->playerId <=> $b->playerId;
    }

    private function compareEliminationStage(StandingRow $a, StandingRow $b): int
    {
        if ($a->eliminatedRound === $b->eliminatedRound) {
            return 0;
        }

        if ($a->eliminatedRound === null) {
            return -1;
        }

        if ($b->eliminatedRound === null) {
            return 1;
        }

        return $b->eliminatedRound <=> $a->eliminatedRound;
    }
}
