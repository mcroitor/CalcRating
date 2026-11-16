<?php

namespace Tests\Unit;

use App\Enums\PairingResult;
use App\Enums\PairingType;
use App\Exceptions\DomainException;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\PairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PairingGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private PairingService $pairings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pairings = app(PairingService::class);
    }

    public function test_round_robin_generates_a_complete_schedule_for_four_players(): void
    {
        $tournament = $this->tournament(PairingType::RoundRobin);
        $players = $this->participants($tournament, 4);

        $rounds = $this->pairings->generate($tournament, $tournament->version);
        $pairings = $tournament->fresh()->pairings()->get();

        $this->assertSame(3, $rounds);
        $this->assertCount(6, $pairings);
        $this->assertSame([1, 2, 3], $pairings->pluck('round_number')->unique()->sort()->values()->all());
        $this->assertSame(2, $pairings->where('round_number', 1)->count());
        $this->assertSame(6, count(array_unique($this->pairKeys($pairings))));

        foreach ($players as $player) {
            $this->assertSame(3, $this->gamesFor($pairings, $player->id));
        }
    }

    public function test_round_robin_generates_a_complete_schedule_for_five_players(): void
    {
        $tournament = $this->tournament(PairingType::RoundRobin);
        $players = $this->participants($tournament, 5);

        $rounds = $this->pairings->generate($tournament, $tournament->version);
        $pairings = $tournament->fresh()->pairings()->get();

        $this->assertSame(5, $rounds);
        $this->assertCount(10, $pairings);
        $this->assertSame(10, count(array_unique($this->pairKeys($pairings))));

        for ($round = 1; $round <= 5; $round++) {
            $this->assertSame(2, $pairings->where('round_number', $round)->count());
        }

        foreach ($players as $player) {
            $this->assertSame(4, $this->gamesFor($pairings, $player->id));
        }
    }

    public function test_round_robin_creates_return_games_when_two_games_per_round_are_configured(): void
    {
        $tournament = $this->tournament(PairingType::RoundRobin, gamesPerRound: 2);
        $this->participants($tournament, 4);

        $this->pairings->generate($tournament, $tournament->version);
        $pairings = $tournament->fresh()->pairings()->get();

        $this->assertCount(12, $pairings);

        $keys = $this->pairKeys($pairings);
        $this->assertCount(6, array_unique($keys));

        foreach (array_count_values($keys) as $count) {
            $this->assertSame(2, $count);
        }

        foreach ($pairings->groupBy(fn ($pairing) => $this->pairKey($pairing->white_player_id, $pairing->black_player_id)) as $pairGames) {
            $pairGames = $pairGames->values();

            $this->assertCount(2, $pairGames);
            $this->assertSame(
                [$pairGames[0]->white_player_id, $pairGames[0]->black_player_id],
                [$pairGames[1]->black_player_id, $pairGames[1]->white_player_id]
            );
        }
    }

    public function test_swiss_generates_three_rounds_without_repeated_pairs(): void
    {
        $tournament = $this->tournament(PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 8);

        for ($round = 1; $round <= 3; $round++) {
            $tournament->refresh();
            $this->assertSame(1, $this->pairings->generate($tournament, $tournament->version));

            $current = $tournament->fresh()->pairings()->where('round_number', $round)->get();
            $this->assertCount(4, $current);

            foreach ($current as $pairing) {
                $this->pairings->saveResult(
                    $tournament->fresh(),
                    $pairing,
                    PairingResult::WhiteWin,
                    null,
                    $pairing->version
                );
            }
        }

        $all = $tournament->fresh()->pairings()->get();

        $this->assertCount(12, $all);
        $this->assertSame(12, count(array_unique($this->pairKeys($all))));
    }

    public function test_swiss_gives_a_bye_to_the_lowest_ranked_players_without_previous_byes(): void
    {
        $tournament = $this->tournament(PairingType::Swiss, rounds: 5);
        $players = $this->participants($tournament, 7);
        $byePlayers = [];

        for ($round = 1; $round <= 2; $round++) {
            $tournament->refresh();
            $this->pairings->generate($tournament, $tournament->version);

            $current = $tournament->fresh()->pairings()->where('round_number', $round)->get();
            $byes = $current->whereNull('black_player_id');

            $this->assertCount(1, $byes);
            $this->assertSame(PairingResult::WhiteWin, $byes->first()->result);

            $byePlayers[] = $byes->first()->white_player_id;

            foreach ($current->whereNotNull('black_player_id') as $pairing) {
                $this->pairings->saveResult(
                    $tournament->fresh(),
                    $pairing,
                    PairingResult::WhiteWin,
                    null,
                    $pairing->version
                );
            }
        }

        $this->assertNotSame($byePlayers[0], $byePlayers[1]);

        $participant = $tournament->fresh()->participants()->where('player_id', $byePlayers[0])->firstOrFail();
        $this->assertSame(1.0, (float) $participant->points);

        $this->assertTrue($players->contains(fn (Player $player) => $player->id === $byePlayers[0]));
    }

    public function test_olympic_first_round_seeds_the_bracket_and_gives_byes_to_top_players(): void
    {
        $tournament = $this->tournament(PairingType::Olympic, rounds: 3);
        $players = $this->participants($tournament, 5);

        $this->pairings->generate($tournament, $tournament->version);

        $first = $tournament->fresh()->pairings()->where('round_number', 1)->get();

        $this->assertCount(4, $first);
        $this->assertSame(3, $first->whereNull('black_player_id')->count());

        $topThree = collect([$players[0]->id, $players[1]->id, $players[2]->id])->sort()->values()->all();
        $byePlayers = $first->whereNull('black_player_id')->pluck('white_player_id')->sort()->values()->all();

        $this->assertSame($topThree, $byePlayers);

        $game = $first->whereNotNull('black_player_id')->first();
        $this->assertNotNull($game);
        $this->assertSame([$players[3]->id, $players[4]->id], [$game->white_player_id, $game->black_player_id]);
    }

    public function test_olympic_draw_blocks_the_next_round_until_an_advanced_player_is_selected(): void
    {
        $tournament = $this->tournament(PairingType::Olympic, rounds: 3);
        $this->participants($tournament, 4);

        $this->pairings->generate($tournament, $tournament->version);

        $first = $tournament->fresh()->pairings()->where('round_number', 1)->get();
        $boardOne = $first->firstWhere('board_number', 1);
        $boardTwo = $first->firstWhere('board_number', 2);

        try {
            $this->pairings->saveResult(
                $tournament->fresh(),
                $boardOne,
                PairingResult::Draw,
                null,
                $boardOne->version
            );
            $this->fail('A draw without an advanced player must be rejected.');
        } catch (DomainException $e) {
            $this->assertStringContainsString(__('pairings.errors.advanced_required'), $e->getMessage());
        }

        $this->assertSame(PairingResult::Pending, $boardOne->fresh()->result);
        $this->assertNotNull($this->pairings->generationBlockReason($tournament->fresh()));

        $this->pairings->saveResult(
            $tournament->fresh(),
            $boardOne->fresh(),
            PairingResult::Draw,
            $boardOne->white_player_id,
            $boardOne->version
        );
        $this->pairings->saveResult(
            $tournament->fresh(),
            $boardTwo->fresh(),
            PairingResult::WhiteWin,
            null,
            $boardTwo->version
        );

        $tournament->refresh();
        $this->assertSame(1, $this->pairings->generate($tournament, $tournament->version));

        $final = $tournament->fresh()->pairings()->where('round_number', 2)->get();

        $this->assertCount(1, $final);
        $this->assertSame($boardOne->white_player_id, $final->first()->white_player_id);
        $this->assertSame($boardTwo->white_player_id, $final->first()->black_player_id);
    }

    public function test_completed_swiss_rounds_cannot_be_generated_beyond_the_configured_limit(): void
    {
        $tournament = $this->tournament(PairingType::Swiss, rounds: 1);
        $this->participants($tournament, 4);

        $this->pairings->generate($tournament, $tournament->version);

        foreach ($tournament->fresh()->pairings()->get() as $pairing) {
            $this->pairings->saveResult(
                $tournament->fresh(),
                $pairing,
                PairingResult::WhiteWin,
                null,
                $pairing->version
            );
        }

        $tournament->refresh();

        $this->assertSame(
            __('pairings.errors.all_rounds_generated'),
            $this->pairings->generationBlockReason($tournament)
        );

        $this->expectException(DomainException::class);
        $this->pairings->generate($tournament, $tournament->version);
    }

    private function tournament(
        PairingType $type,
        int $rounds = 3,
        int $gamesPerRound = 1
    ): Tournament {
        return Tournament::factory()->inProgress()->create([
            'pairing_type' => $type,
            'rounds_count' => $rounds,
            'games_per_round' => $gamesPerRound,
        ]);
    }

    /**
     * @return Collection<int, Player>
     */
    private function participants(Tournament $tournament, int $count)
    {
        return collect(range(1, $count))->map(function (int $index) use ($tournament) {
            $player = Player::factory()->create([
                'first_name' => 'Участник',
                'last_name' => "Игрок {$index}",
                'current_rating' => 2000 - ($index * 10),
            ]);

            $tournament->participants()->create([
                'player_id' => $player->id,
                'points' => 0,
                'rating_before' => $player->current_rating,
                'version' => 1,
            ]);

            return $player;
        });
    }

    /**
     * @param  Collection<int, Pairing>  $pairings
     * @return list<string>
     */
    private function pairKeys($pairings): array
    {
        return $pairings
            ->filter(fn ($pairing) => $pairing->black_player_id !== null)
            ->map(fn ($pairing) => $this->pairKey($pairing->white_player_id, $pairing->black_player_id))
            ->values()
            ->all();
    }

    private function pairKey(int $first, int $second): string
    {
        return $first < $second ? "{$first}-{$second}" : "{$second}-{$first}";
    }

    /**
     * @param  Collection<int, Pairing>  $pairings
     */
    private function gamesFor($pairings, int $playerId): int
    {
        return $pairings
            ->filter(fn ($pairing) => in_array($playerId, [$pairing->white_player_id, $pairing->black_player_id], true))
            ->count();
    }
}
