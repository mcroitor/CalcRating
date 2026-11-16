<?php

namespace Tests\Feature;

use App\Enums\PairingResult;
use App\Enums\PairingType;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PairingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_arbiter_can_generate_a_swiss_round(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 4);

        $response = $this->generate($arbiter, $tournament);

        $response->assertRedirect(route('tournaments.show', $tournament));
        $response->assertSessionHas('success', __('pairings.flash.generated_round'));

        $tournament->refresh();
        $this->assertSame(2, $tournament->version);
        $this->assertCount(2, $tournament->pairings()->where('round_number', 1)->get());
    }

    public function test_arbiter_sees_generation_and_result_controls_on_the_tournament_page(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 4);

        $this->actingAs($arbiter)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee(__('tournaments.show.pairings_title'))
            ->assertSee(__('tournaments.show.generate_round', ['round' => 1]));

        $this->generate($arbiter, $tournament);

        $this->actingAs($arbiter)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee(__('tournaments.show.round', ['round' => 1]))
            ->assertSee(__('common.save'))
            ->assertSee(__('pairings.errors.finish_current_round'));
    }

    public function test_saving_a_result_recalculates_points_and_bumps_the_participant_version(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament);

        $pairing = $tournament->refresh()->pairings()->firstOrFail();
        $participant = $tournament->participants()
            ->where('player_id', $pairing->white_player_id)
            ->firstOrFail();

        $this->assertSame(1, $participant->version);

        $this->saveResult($arbiter, $tournament, $pairing, PairingResult::WhiteWin)
            ->assertRedirect(route('tournaments.show', $tournament));

        $participant->refresh();

        $this->assertSame(1.0, (float) $participant->points);
        $this->assertSame(2, $participant->version);
    }

    public function test_arbiter_sees_advanced_player_radios_for_olympic_pairings(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Olympic, rounds: 3);
        $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament);

        $this->actingAs($arbiter)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee(__('tournaments.show.advanced_legend'))
            ->assertSee('type="radio"', false);
    }

    public function test_guest_can_see_the_schedule_but_not_generation_controls(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $players = $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament)->assertRedirect();

        $response = $this->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertSee(__('tournaments.show.pairings_title'));
        $response->assertSee(__('tournaments.show.round', ['round' => 1]));
        $response->assertSee(__('tournaments.show.col_board'));
        $response->assertSee($players[0]->last_name);
        $response->assertDontSee(__('tournaments.show.generate_round', ['round' => 1]));
        $response->assertDontSee(__('tournaments.show.generate_schedule'));
    }

    public function test_guest_role_cannot_generate_rounds_or_save_results(): void
    {
        $guest = User::factory()->guest()->create();
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament)->assertRedirect();

        $pairing = $tournament->refresh()->pairings()->firstOrFail();

        $this->actingAs($guest)
            ->post(route('tournaments.rounds.generate', $tournament), ['version' => $tournament->version])
            ->assertForbidden();

        $this->actingAs($guest)
            ->put(route('tournaments.pairings.result', [$tournament, $pairing]), [
                'result' => PairingResult::WhiteWin->value,
                'version' => $pairing->version,
            ])
            ->assertForbidden();
    }

    public function test_swiss_standings_use_head_to_head_derived_from_saved_results(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $players = $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament);

        $firstRound = $tournament->refresh()->pairings()->where('round_number', 1)->get();
        $boardOne = $firstRound->firstWhere('board_number', 1);
        $boardTwo = $firstRound->firstWhere('board_number', 2);

        // The lower-rated player wins the first board; the higher-rated player
        // wins the second board, so round two pairs by points.
        $this->saveResult($arbiter, $tournament, $boardOne, PairingResult::BlackWin);
        $this->saveResult($arbiter, $tournament, $boardTwo, PairingResult::WhiteWin);

        $this->generate($arbiter, $tournament);

        $secondRound = $tournament->fresh()->pairings()->where('round_number', 2)->get();
        $this->assertCount(2, $secondRound);

        // P3 beats P2, while P1 beats P4: P2 and P1 finish on one point.
        $this->saveResult($arbiter, $tournament, $secondRound->firstWhere('board_number', 1), PairingResult::BlackWin);
        $this->saveResult($arbiter, $tournament, $secondRound->firstWhere('board_number', 2), PairingResult::WhiteWin);

        $standings = app(StandingsService::class)->build($tournament->fresh());

        // P3 has 2 points. P2 and P1 both have 1 point, and P2 beat P1
        // head-to-head even though P1 has the higher starting rating.
        $this->assertSame(
            [$players[2]->id, $players[1]->id, $players[0]->id, $players[3]->id],
            $standings->pluck('playerId')->all()
        );
    }

    public function test_olympic_standings_are_ranked_by_elimination_stage(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Olympic, rounds: 3);
        $players = $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament);

        $firstRound = $tournament->refresh()->pairings()->where('round_number', 1)->get();
        $boardOne = $firstRound->firstWhere('board_number', 1);
        $boardTwo = $firstRound->firstWhere('board_number', 2);

        $this->saveResult($arbiter, $tournament, $boardOne, PairingResult::WhiteWin);
        $this->saveResult($arbiter, $tournament, $boardTwo, PairingResult::WhiteWin);

        $this->generate($arbiter, $tournament);

        $final = $tournament->fresh()->pairings()->where('round_number', 2)->firstOrFail();
        $this->saveResult($arbiter, $tournament, $final, PairingResult::BlackWin);

        $standings = app(StandingsService::class)->build($tournament->fresh());

        // P2 is the champion, P1 lost the final, P3 and P4 lost in round one.
        $this->assertSame(
            [$players[1]->id, $players[0]->id, $players[2]->id, $players[3]->id],
            $standings->pluck('playerId')->all()
        );
    }

    public function test_stale_pairing_result_returns_409_and_is_not_overwritten(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament);

        $pairing = $tournament->refresh()->pairings()->firstOrFail();
        $this->assertSame(1, $pairing->version);

        $this->saveResult($arbiter, $tournament, $pairing, PairingResult::WhiteWin)
            ->assertRedirect(route('tournaments.show', $tournament));

        $response = $this->actingAs($arbiter)->put(
            route('tournaments.pairings.result', [$tournament, $pairing]),
            [
                'result' => PairingResult::BlackWin->value,
                'version' => 1,
            ]
        );

        $response->assertStatus(409);
        $response->assertSee(__('errors.409.message'));

        $this->assertSame(PairingResult::WhiteWin, $pairing->fresh()->result);
        $this->assertSame(2, $pairing->fresh()->version);
    }

    public function test_stale_tournament_version_blocks_generation_with_409(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 4);

        $tournament->updateWithVersion(['location' => 'Другой зал'], 1);

        $response = $this->actingAs($arbiter)->post(
            route('tournaments.rounds.generate', $tournament),
            ['version' => 1]
        );

        $response->assertStatus(409);
        $response->assertSee(__('errors.409.message'));

        $this->assertSame(0, $tournament->fresh()->pairings()->count());
    }

    public function test_round_robin_generation_creates_the_full_schedule(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::RoundRobin, rounds: 5);
        $this->participants($tournament, 4);

        $response = $this->generate($arbiter, $tournament);

        $response->assertSessionHas('success', __('pairings.flash.generated_rounds', ['rounds' => 3]));

        $this->assertSame(3, $tournament->fresh()->pairings()->pluck('round_number')->unique()->count());
        $this->assertSame(6, $tournament->fresh()->pairings()->count());
    }

    public function test_generation_is_rejected_until_every_board_of_the_current_round_is_finished(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, PairingType::Swiss, rounds: 3);
        $this->participants($tournament, 4);

        $this->generate($arbiter, $tournament);

        $pairing = $tournament->refresh()->pairings()->firstOrFail();
        $this->saveResult($arbiter, $tournament, $pairing, PairingResult::WhiteWin);

        $response = $this->generate($arbiter, $tournament);

        $response->assertSessionHas('error');
        $this->assertSame(1, $tournament->fresh()->pairings()->pluck('round_number')->unique()->count());
    }

    private function tournament(User $arbiter, PairingType $type, int $rounds = 3): Tournament
    {
        return Tournament::factory()->inProgress()->create([
            'arbiter_id' => $arbiter->id,
            'pairing_type' => $type,
            'rounds_count' => $rounds,
            'games_per_round' => 1,
            'version' => 1,
        ]);
    }

    /**
     * @return list<Player>
     */
    private function participants(Tournament $tournament, int $count): array
    {
        $players = [];

        for ($index = 1; $index <= $count; $index++) {
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

            $players[] = $player;
        }

        return $players;
    }

    private function generate(User $arbiter, Tournament $tournament): TestResponse
    {
        return $this->actingAs($arbiter)->post(
            route('tournaments.rounds.generate', $tournament),
            ['version' => $tournament->fresh()->version]
        );
    }

    private function saveResult(
        User $arbiter,
        Tournament $tournament,
        Pairing $pairing,
        PairingResult $result
    ): TestResponse {
        return $this->actingAs($arbiter)->put(
            route('tournaments.pairings.result', [$tournament, $pairing]),
            [
                'result' => $result->value,
                'version' => $pairing->fresh()->version,
            ]
        );
    }
}
