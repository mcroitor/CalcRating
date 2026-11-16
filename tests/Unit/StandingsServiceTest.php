<?php

namespace Tests\Unit;

use App\Enums\PairingType;
use App\Services\StandingsService;
use App\Support\StandingRow;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StandingsServiceTest extends TestCase
{
    private StandingsService $standings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->standings = new StandingsService;
    }

    public function test_points_are_the_primary_sort_key(): void
    {
        $sorted = $this->sort([
            $this->row(1, 2.5),
            $this->row(2, 3.0),
            $this->row(3, 0.0),
        ]);

        $this->assertSame([2, 1, 3], $this->ids($sorted));
    }

    public function test_head_to_head_breaks_a_tie_of_exactly_two_participants(): void
    {
        $sorted = $this->sort([
            $this->row(1, 2.0, headToHead: [2 => 1.0], ratingBefore: 1000),
            $this->row(2, 2.0, headToHead: [1 => 0.0], ratingBefore: 2000),
        ]);

        $this->assertSame([1, 2], $this->ids($sorted));
    }

    public function test_head_to_head_is_ignored_when_three_or_more_share_the_score(): void
    {
        $sorted = $this->sort([
            $this->row(1, 2.0, wins: 1, headToHead: [2 => 1.0, 3 => 1.0], ratingBefore: 2000),
            $this->row(2, 2.0, wins: 2, headToHead: [1 => 0.0], ratingBefore: 1500),
            $this->row(3, 2.0, wins: 1, headToHead: [1 => 0.0], ratingBefore: 1800),
        ]);

        // Number of wins outweighs head-to-head and rating; then rating, then ID.
        $this->assertSame([2, 1, 3], $this->ids($sorted));
    }

    public function test_rating_and_id_finish_the_tiebreak_chain(): void
    {
        $sorted = $this->sort([
            $this->row(30, 1.0, wins: 1, ratingBefore: 1800),
            $this->row(10, 1.0, wins: 1, ratingBefore: 1800),
            $this->row(20, 1.0, wins: 1, ratingBefore: 1600),
        ]);

        $this->assertSame([10, 30, 20], $this->ids($sorted));
    }

    public function test_order_is_stable_regardless_of_input_order(): void
    {
        $rows = collect([
            $this->row(1, 2.0, wins: 1, ratingBefore: 1700),
            $this->row(2, 2.0, wins: 1, ratingBefore: 1700),
            $this->row(3, 2.0, wins: 0, ratingBefore: 2000),
            $this->row(4, 1.0, wins: 1, ratingBefore: 1200),
        ]);

        $expected = $this->ids($this->standings->sort($rows));

        for ($i = 0; $i < 25; $i++) {
            $this->assertSame($expected, $this->ids($this->standings->sort($rows->shuffle())));
        }

        $this->assertSame([1, 2, 3, 4], $expected);
    }

    public function test_olympic_standings_are_ordered_by_elimination_stage_then_points(): void
    {
        $sorted = $this->standings->sort(collect([
            $this->row(1, 1.0, eliminatedRound: null), // champion
            $this->row(2, 4.0, eliminatedRound: 2),    // finalist
            $this->row(3, 5.0, eliminatedRound: 1),    // eliminated in round 1
            $this->row(4, 3.0, eliminatedRound: 1),
        ]), PairingType::Olympic);

        $this->assertSame([1, 2, 3, 4], $this->ids($sorted));
    }

    /**
     * @param  list<StandingRow>  $rows
     */
    private function sort(array $rows): Collection
    {
        return $this->standings->sort(collect($rows));
    }

    /**
     * @param  Collection<int, StandingRow>  $rows
     * @return list<int>
     */
    private function ids(Collection $rows): array
    {
        return $rows->pluck('playerId')->all();
    }

    /**
     * @param  array<int, float>  $headToHead
     */
    private function row(
        int $playerId,
        float $points,
        int $wins = 0,
        array $headToHead = [],
        int $ratingBefore = 1000,
        ?int $eliminatedRound = null
    ): StandingRow {
        return new StandingRow(
            participantId: $playerId,
            playerId: $playerId,
            playerName: "Player {$playerId}",
            points: $points,
            wins: $wins,
            headToHead: $headToHead,
            ratingBefore: $ratingBefore,
            eliminatedRound: $eliminatedRound,
        );
    }
}
