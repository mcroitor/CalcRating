<?php

namespace Tests\Feature;

use App\Enums\PairingResult;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Rating;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TournamentCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $arbiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arbiter = User::factory()->arbiter()->create();
    }

    public function test_completing_tournament_calculates_and_freezes_ratings(): void
    {
        [$tournament, $white, $black] = $this->readyTournament(RatingMode::Average);

        $initialRating = Rating::create([
            'player_id' => $white->id,
            'value' => 1980,
            'created_at' => now()->subDay(),
        ]);
        Rating::create([
            'player_id' => $black->id,
            'value' => 1780,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->arbiter)->post(
            route('tournaments.complete', $tournament),
            ['version' => $tournament->fresh()->version]
        );

        $response->assertRedirect(route('tournaments.show', $tournament));
        $response->assertSessionHas('success');

        $tournament->refresh();
        $this->assertSame(TournamentStatus::Completed, $tournament->status);

        $participants = $tournament->participants()->get()->keyBy('player_id');
        $this->assertSame(2010, (int) $participants[$white->id]->rating_after);
        $this->assertSame(1790, (int) $participants[$black->id]->rating_after);

        $white->refresh();
        $black->refresh();
        $this->assertSame(2010, $white->current_rating);
        $this->assertSame(1790, $black->current_rating);

        $this->assertDatabaseHas('ratings', [
            'player_id' => $white->id,
            'tournament_id' => $tournament->id,
            'value' => 2010,
        ]);
        $this->assertDatabaseHas('ratings', [
            'player_id' => $black->id,
            'tournament_id' => $tournament->id,
            'value' => 1790,
        ]);

        // Historical records are preserved untouched.
        $this->assertDatabaseHas('ratings', [
            'id' => $initialRating->id,
            'value' => 1980,
        ]);
    }

    public function test_excluded_results_do_not_change_rating(): void
    {
        [$tournament] = $this->readyTournament(RatingMode::Average);

        // Replace the rated game with an excluded double forfeit: N=0 for both.
        $tournament->fresh()->pairings()->delete();
        $participants = $tournament->participants()->get()->values();
        $first = $participants[0];
        $second = $participants[1];

        $tournament->pairings()->create([
            'round_number' => 1,
            'board_number' => 1,
            'white_player_id' => $first->player_id,
            'black_player_id' => $second->player_id,
            'result' => '0-0',
            'version' => 1,
        ]);

        $this->actingAs($this->arbiter)->post(
            route('tournaments.complete', $tournament),
            ['version' => $tournament->fresh()->version]
        );

        $tournament->refresh();
        $this->assertSame(TournamentStatus::Completed, $tournament->status);

        foreach ($participants as $participant) {
            $participant->refresh();
            $player = $participant->player;
            $player->refresh();
            $this->assertSame($participant->rating_before, (int) $participant->rating_after);
            $this->assertSame($participant->rating_before, (int) $player->current_rating);
        }
    }

    public function test_completion_requires_all_results(): void
    {
        [$tournament] = $this->readyTournament(RatingMode::Average, withResult: false);

        $response = $this->actingAs($this->arbiter)->post(
            route('tournaments.complete', $tournament),
            ['version' => $tournament->fresh()->version]
        );

        $response->assertSessionHas('error');
        $tournament->refresh();
        $this->assertSame(TournamentStatus::InProgress, $tournament->status);
    }

    public function test_overlapping_tournament_requires_confirmation_and_writes_audit(): void
    {
        [$tournament, $white, $black] = $this->readyTournament(RatingMode::Average);

        // simulate another tournament changing the white player's current rating
        $white->updateWithVersion(['current_rating' => 2100], $white->version);

        $response = $this->actingAs($this->arbiter)->post(
            route('tournaments.complete', $tournament),
            ['version' => $tournament->fresh()->version]
        );

        $response->assertSessionHas('error');
        $tournament->refresh();
        $this->assertSame(TournamentStatus::InProgress, $tournament->status);

        Log::spy();
        Log::shouldReceive('channel')->with('audit')->andReturnSelf();

        $response = $this->actingAs($this->arbiter)->post(
            route('tournaments.complete', $tournament),
            [
                'version' => $tournament->fresh()->version,
                'confirm_overlap' => '1',
            ]
        );

        $response->assertRedirect(route('tournaments.show', $tournament));
        $tournament->refresh();
        $this->assertSame(TournamentStatus::Completed, $tournament->status);

        $white->refresh();
        $this->assertSame(2010, $white->current_rating);

        Log::shouldHaveReceived('info')->with('tournament.completed', \Mockery::type('array'))->atLeast()->once();
        Log::shouldHaveReceived('info')->with('rating.changed', \Mockery::type('array'))->atLeast()->once();
    }

    public function test_stale_tournament_version_returns_409(): void
    {
        [$tournament] = $this->readyTournament(RatingMode::Average);

        $tournament->updateWithVersion(['name' => 'Изменено конкурентом'], $tournament->version);

        $response = $this->actingAs($this->arbiter)->post(
            route('tournaments.complete', $tournament),
            ['version' => 1]
        );

        $response->assertStatus(409);
        $response->assertSee(__('errors.409.message'));

        $tournament->refresh();
        $this->assertSame(TournamentStatus::InProgress, $tournament->status);
    }

    public function test_guest_can_see_final_ratings_and_deltas(): void
    {
        [$tournament] = $this->readyTournament(RatingMode::Average);

        $this->actingAs($this->arbiter)->post(
            route('tournaments.complete', $tournament),
            ['version' => $tournament->fresh()->version]
        );

        $response = $this->actingAs($this->arbiter)->get(route('tournaments.show', $tournament));
        $response->assertOk();
        $response->assertSee('+10');
        $response->assertSee('-10');

        $guest = User::factory()->guest()->create();
        $response = $this->actingAs($guest)->get(route('tournaments.show', $tournament));
        $response->assertOk();
        $response->assertSee(TournamentStatus::Completed->label());
        $response->assertDontSee(__('tournaments.show.complete'));
        $response->assertDontSee(__('common.save'));
    }

    public function test_guest_role_cannot_complete_tournament(): void
    {
        [$tournament] = $this->readyTournament(RatingMode::Average);

        $guest = User::factory()->guest()->create();

        $response = $this->actingAs($guest)->post(route('tournaments.complete', $tournament), [
            'version' => $tournament->fresh()->version,
        ]);

        $response->assertForbidden();
    }

    public function test_completion_button_appears_after_all_results_saved(): void
    {
        [$tournament] = $this->readyTournament(RatingMode::Average, withResult: false);

        $this->actingAs($this->arbiter)->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertDontSee(__('tournaments.show.complete'));

        $pairing = $tournament->fresh()->pairings()->firstOrFail();
        $this->saveResult($this->arbiter, $tournament, $pairing, PairingResult::WhiteWin);

        $this->actingAs($this->arbiter)->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee(__('tournaments.show.complete'));
    }

    /**
     * @return array{0: Tournament, 1: Player, 2: Player}
     */
    private function readyTournament(RatingMode $mode, bool $withResult = true): array
    {
        $tournament = $this->tournament($this->arbiter, $mode);

        $white = Player::factory()->create(['current_rating' => 2000]);
        $black = Player::factory()->create(['current_rating' => 1800]);

        foreach ([$white, $black] as $player) {
            $tournament->participants()->create([
                'player_id' => $player->id,
                'points' => 0,
                'rating_before' => $player->current_rating,
                'version' => 1,
            ]);
        }

        $tournament->pairings()->create([
            'round_number' => 1,
            'board_number' => 1,
            'white_player_id' => $white->id,
            'black_player_id' => $black->id,
            'result' => $withResult ? '1-0' : '*',
            'version' => 1,
        ]);

        $tournament->updateWithVersion(['status' => TournamentStatus::InProgress->value], $tournament->version);

        return [$tournament->fresh(), $white, $black];
    }

    private function tournament(User $arbiter, RatingMode $mode): Tournament
    {
        return Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'rating_mode' => $mode,
            'pairing_type' => PairingType::Swiss,
            'rounds_count' => 3,
            'games_per_round' => 1,
            'status' => TournamentStatus::Draft,
            'version' => 1,
        ]);
    }

    private function participants(Tournament $tournament, int $count): array
    {
        $players = [];
        for ($i = 1; $i <= $count; $i++) {
            $player = Player::factory()->create(['current_rating' => 2000 - ($i * 10)]);
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

    private function saveAllPendingExceptSample(Tournament $tournament, array $players): void
    {
        // noop helper retained for clarity
    }
}
