<?php

namespace Tests\Feature;

use App\Enums\ClubStaffRole;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Models\Club;
use App\Models\Player;
use App\Models\Rating;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_tournaments_index_and_show(): void
    {
        $arbiter = User::factory()->arbiter()->create([
            'name' => 'Вильгельм',
            'surname' => 'Стейниц',
        ]);

        $player = Player::factory()->create([
            'first_name' => 'Михаил',
            'last_name' => 'Таль',
            'current_rating' => 2705,
        ]);

        $tournament = $this->tournament($arbiter, [
            'name' => 'Мемориал Таля',
            'rating_mode' => RatingMode::PerGame,
        ]);

        $this->addParticipant($tournament, $player);

        $this->get('/tournaments')
            ->assertOk()
            ->assertSee('Мемориал Таля')
            ->assertSee('Стейниц');

        $this->get("/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertSee('Мемориал Таля')
            ->assertSee('Таль Михаил')
            ->assertSee(RatingMode::PerGame->label())
            ->assertSee(__('tournaments.show.standings'));
    }

    public function test_guest_cannot_mutate_tournaments(): void
    {
        $tournament = Tournament::factory()->create();
        $participant = $this->addParticipant($tournament, Player::factory()->create());

        $this->get('/tournaments/create')->assertRedirect(route('login'));
        $this->post('/tournaments', [])->assertRedirect(route('login'));
        $this->get("/tournaments/{$tournament->id}/edit")->assertRedirect(route('login'));
        $this->put("/tournaments/{$tournament->id}", [])->assertRedirect(route('login'));
        $this->delete("/tournaments/{$tournament->id}")->assertRedirect(route('login'));
        $this->post("/tournaments/{$tournament->id}/start", [])->assertRedirect(route('login'));
        $this->post("/tournaments/{$tournament->id}/participants", [])->assertRedirect(route('login'));
        $this->delete("/tournaments/{$tournament->id}/participants/{$participant->id}")->assertRedirect(route('login'));
    }

    public function test_user_with_guest_role_receives_403_on_mutation_routes(): void
    {
        $guest = User::factory()->guest()->create();
        $tournament = Tournament::factory()->create();
        $participant = $this->addParticipant($tournament, Player::factory()->create());

        $this->actingAs($guest)->get('/tournaments/create')->assertForbidden();
        $this->actingAs($guest)->post('/tournaments', [])->assertForbidden();
        $this->actingAs($guest)->get("/tournaments/{$tournament->id}/edit")->assertForbidden();
        $this->actingAs($guest)->put("/tournaments/{$tournament->id}", [])->assertForbidden();
        $this->actingAs($guest)->delete("/tournaments/{$tournament->id}")->assertForbidden();
        $this->actingAs($guest)->post("/tournaments/{$tournament->id}/start", [])->assertForbidden();
        $this->actingAs($guest)->post("/tournaments/{$tournament->id}/participants", [])->assertForbidden();
        $this->actingAs($guest)->delete("/tournaments/{$tournament->id}/participants/{$participant->id}")->assertForbidden();
    }

    public function test_arbiter_can_create_tournament_and_becomes_its_arbiter(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $club = $this->clubWithManager($arbiter);

        $response = $this->actingAs($arbiter)->post('/tournaments', $this->payload([
            'club_id' => $club->id,
            'name' => 'Открытый чемпионат клуба',
        ]));

        $tournament = Tournament::firstOrFail();

        $response->assertRedirect(route('tournaments.show', $tournament));

        $this->assertSame($club->id, $tournament->club_id);
        $this->assertSame($arbiter->id, $tournament->arbiter_id);
        $this->assertSame('Открытый чемпионат клуба', $tournament->name);
        $this->assertSame(PairingType::Swiss, $tournament->pairing_type);
        $this->assertSame(RatingMode::Average, $tournament->rating_mode);
        $this->assertSame(TournamentStatus::Draft, $tournament->status);
        $this->assertSame(1, $tournament->version);
    }

    public function test_admin_can_assign_another_arbiter_when_creating_tournament(): void
    {
        $admin = User::factory()->admin()->create();
        $arbiter = User::factory()->arbiter()->create();
        $club = $this->clubWithManager($admin);
        $club->staff()->attach($arbiter->id, ['role' => ClubStaffRole::Arbiter->value]);

        $this->actingAs($admin)->post('/tournaments', $this->payload([
            'club_id' => $club->id,
            'arbiter_id' => $arbiter->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'club_id' => $club->id,
            'name' => 'Открытый турнир',
            'arbiter_id' => $arbiter->id,
        ]);
    }

    public function test_tournament_validation_rules(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $club = $this->clubWithManager($arbiter);

        $this->actingAs($arbiter)->post('/tournaments', $this->payload([
            'club_id' => $club->id,
            'end_date' => now()->subDay()->format('Y-m-d'),
        ]))->assertSessionHasErrors('end_date');

        $this->actingAs($arbiter)->post('/tournaments', $this->payload([
            'club_id' => $club->id,
            'rounds_count' => 0,
        ]))->assertSessionHasErrors('rounds_count');

        $this->actingAs($arbiter)->post('/tournaments', $this->payload([
            'club_id' => $club->id,
            'pairing_type' => 'unknown',
        ]))->assertSessionHasErrors('pairing_type');

        $this->actingAs($arbiter)->post('/tournaments', $this->payload([
            'club_id' => $club->id,
            'rating_mode' => 'unknown',
        ]))->assertSessionHasErrors('rating_mode');
    }

    public function test_arbiter_can_open_create_and_edit_forms(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $this->clubWithManager($arbiter);

        $this->actingAs($arbiter)
            ->get('/tournaments/create')
            ->assertOk()
            ->assertSee(__('tournaments.create.title'))
            ->assertSee(RatingMode::Average->label());

        $tournament = $this->tournament($arbiter, ['name' => 'Форма редактирования']);

        $this->actingAs($arbiter)
            ->get("/tournaments/{$tournament->id}/edit")
            ->assertOk()
            ->assertSee('Форма редактирования')
            ->assertSee(__('tournaments.edit.submit'));
    }

    public function test_admin_sees_arbiter_select_on_create_form(): void
    {
        $admin = User::factory()->admin()->create();
        $arbiter = User::factory()->arbiter()->create([
            'name' => 'Арбитр',
            'surname' => 'Тестовый',
        ]);
        $club = $this->clubWithManager($admin);
        $club->staff()->attach($arbiter->id, ['role' => ClubStaffRole::Arbiter->value]);

        $this->actingAs($admin)
            ->get('/tournaments/create')
            ->assertOk()
            ->assertSee(__('tournaments.form.arbiter'))
            ->assertSee('Тестовый Арбитр');
    }

    public function test_arbiter_can_update_draft_tournament(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $response = $this->actingAs($arbiter)->put("/tournaments/{$tournament->id}", $this->payload([
            'name' => 'Обновлённый турнир',
            'rating_mode' => RatingMode::PerGame->value,
            'version' => $tournament->version,
        ]));

        $response->assertRedirect(route('tournaments.show', $tournament));

        $tournament->refresh();
        $this->assertSame('Обновлённый турнир', $tournament->name);
        $this->assertSame(RatingMode::PerGame, $tournament->rating_mode);
        $this->assertSame(2, $tournament->version);
    }

    public function test_tournament_update_fails_with_409_on_stale_version(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $tournament->updateWithVersion(['name' => 'Изменено конкурентом'], 1);

        $response = $this->actingAs($arbiter)->put("/tournaments/{$tournament->id}", $this->payload([
            'name' => 'Опоздавшее изменение',
            'version' => 1,
        ]));

        $response->assertStatus(409);
        $response->assertSee(__('errors.409.message'));

        $tournament->refresh();
        $this->assertSame('Изменено конкурентом', $tournament->name);
        $this->assertSame(2, $tournament->version);
    }

    public function test_rating_mode_and_tournament_parameters_are_locked_after_start(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $this->addParticipant($tournament, Player::factory()->create());
        $this->addParticipant($tournament, Player::factory()->create());
        $this->startTournament($arbiter, $tournament);

        $tournament->refresh();

        $response = $this->actingAs($arbiter)->put("/tournaments/{$tournament->id}", $this->payload([
            'rating_mode' => RatingMode::PerGame->value,
            'version' => $tournament->version,
        ]));

        $response->assertSessionHasErrors('rating_mode');

        $tournament->refresh();
        $this->assertSame(RatingMode::Average, $tournament->rating_mode);
        $this->assertSame(2, $tournament->version);

        // Non-structural fields remain editable after start.
        $response = $this->actingAs($arbiter)->put("/tournaments/{$tournament->id}", [
            'name' => 'Турнир после старта',
            'start_date' => $tournament->start_date->format('Y-m-d'),
            'end_date' => $tournament->end_date?->format('Y-m-d'),
            'version' => $tournament->version,
        ]);

        $response->assertRedirect(route('tournaments.show', $tournament));

        $tournament->refresh();
        $this->assertSame('Турнир после старта', $tournament->name);
        $this->assertSame(RatingMode::Average, $tournament->rating_mode);
        $this->assertSame(3, $tournament->version);
    }

    public function test_start_tournament_freezes_actual_ratings(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $first = Player::factory()->create(['current_rating' => 1500]);
        $second = Player::factory()->create(['current_rating' => 1600]);

        $firstParticipant = $this->addParticipant($tournament, $first);
        $secondParticipant = $this->addParticipant($tournament, $second);

        // Ratings changed after registration but before the start.
        $first->update(['current_rating' => 1700]);
        $second->update(['current_rating' => 1800]);

        $this->startTournament($arbiter, $tournament);

        $tournament->refresh();
        $this->assertSame(TournamentStatus::InProgress, $tournament->status);
        $this->assertSame(2, $tournament->version);

        $firstParticipant->refresh();
        $secondParticipant->refresh();

        $this->assertSame(1700, $firstParticipant->rating_before);
        $this->assertSame(1800, $secondParticipant->rating_before);
        $this->assertSame(2, $firstParticipant->version);
        $this->assertSame(2, $secondParticipant->version);

        // Starting a tournament does not add rating history entries yet.
        $this->assertSame(1, $first->ratings()->count());
        $this->assertSame(1, $second->ratings()->count());

        // Ratings are frozen after the start.
        $first->update(['current_rating' => 1900]);
        $firstParticipant->refresh();
        $this->assertSame(1700, $firstParticipant->rating_before);
    }

    public function test_start_requires_at_least_two_participants(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $this->addParticipant($tournament, Player::factory()->create());

        $response = $this->actingAs($arbiter)->post("/tournaments/{$tournament->id}/start", [
            'version' => $tournament->version,
        ]);

        $response->assertSessionHas('error');

        $tournament->refresh();
        $this->assertSame(TournamentStatus::Draft, $tournament->status);
        $this->assertSame(1, $tournament->version);
    }

    public function test_start_fails_with_409_on_stale_tournament_version(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $this->addParticipant($tournament, Player::factory()->create());
        $this->addParticipant($tournament, Player::factory()->create());

        $tournament->updateWithVersion(['name' => 'Изменён состав'], 1);

        $response = $this->actingAs($arbiter)->post("/tournaments/{$tournament->id}/start", [
            'version' => 1,
        ]);

        $response->assertStatus(409);

        $tournament->refresh();
        $this->assertSame(TournamentStatus::Draft, $tournament->status);
        $this->assertSame(2, $tournament->version);
    }

    public function test_roster_cannot_be_changed_after_start(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, ['status' => TournamentStatus::InProgress]);
        $player = Player::factory()->create();
        $participant = $this->addParticipant($tournament, $player);

        $this->actingAs($arbiter)
            ->post("/tournaments/{$tournament->id}/participants", [
                'player_id' => Player::factory()->create()->id,
                'version' => $tournament->version,
            ])
            ->assertForbidden();

        $this->actingAs($arbiter)
            ->delete("/tournaments/{$tournament->id}/participants/{$participant->id}", [
                'version' => $tournament->version,
            ])
            ->assertForbidden();

        $this->assertSame(1, $tournament->participants()->count());
    }

    public function test_duplicate_participant_is_rejected(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);
        $player = Player::factory()->create();

        $this->actingAs($arbiter)
            ->post("/tournaments/{$tournament->id}/participants", [
                'player_id' => $player->id,
                'version' => 1,
            ])
            ->assertRedirect(route('tournaments.show', $tournament));

        $tournament->refresh();

        $response = $this->actingAs($arbiter)
            ->post("/tournaments/{$tournament->id}/participants", [
                'player_id' => $player->id,
                'version' => $tournament->version,
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, $tournament->participants()->count());
    }

    public function test_concurrent_roster_change_returns_409(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);
        $first = Player::factory()->create();
        $second = Player::factory()->create();

        $this->actingAs($arbiter)
            ->post("/tournaments/{$tournament->id}/participants", [
                'player_id' => $first->id,
                'version' => 1,
            ])
            ->assertRedirect(route('tournaments.show', $tournament));

        $tournament->refresh();
        $this->assertSame(2, $tournament->version);

        $response = $this->actingAs($arbiter)
            ->post("/tournaments/{$tournament->id}/participants", [
                'player_id' => $second->id,
                'version' => 1,
            ]);

        $response->assertStatus(409);
        $response->assertSee(__('errors.409.message'));

        $this->assertDatabaseMissing('tournament_participants', [
            'tournament_id' => $tournament->id,
            'player_id' => $second->id,
        ]);
    }

    public function test_arbiter_cannot_delete_tournament(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $this->actingAs($arbiter)
            ->delete("/tournaments/{$tournament->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tournaments', ['id' => $tournament->id]);
    }

    public function test_admin_can_delete_completed_tournament_and_keep_rating_history(): void
    {
        $admin = User::factory()->admin()->create();
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter, ['status' => TournamentStatus::Completed]);
        $player = Player::factory()->create();

        $rating = Rating::create([
            'player_id' => $player->id,
            'tournament_id' => $tournament->id,
            'value' => 1650,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete("/tournaments/{$tournament->id}")
            ->assertRedirect(route('tournaments.index'));

        $this->assertDatabaseMissing('tournaments', ['id' => $tournament->id]);
        $this->assertDatabaseHas('ratings', [
            'id' => $rating->id,
            'tournament_id' => null,
            'value' => 1650,
        ]);
    }

    public function test_tournament_standings_use_rating_and_id_tiebreaks_from_database(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->tournament($arbiter);

        $first = Player::factory()->create(['last_name' => 'Первый']);
        $second = Player::factory()->create(['last_name' => 'Второй']);
        $third = Player::factory()->create(['last_name' => 'Третий']);

        $this->addParticipant($tournament, $first, ['points' => 2.0, 'rating_before' => 1500]);
        $this->addParticipant($tournament, $second, ['points' => 2.0, 'rating_before' => 1900]);
        $this->addParticipant($tournament, $third, ['points' => 2.0, 'rating_before' => 1700]);

        $standings = app(StandingsService::class)->build($tournament->fresh());

        $this->assertSame(
            [$second->id, $third->id, $first->id],
            $standings->pluck('playerId')->all()
        );
    }

    private function clubWithManager(User $manager): Club
    {
        $club = Club::factory()->create();
        $club->staff()->attach($manager->id, ['role' => ClubStaffRole::Manager->value]);

        return $club;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function tournament(User $arbiter, array $overrides = []): Tournament
    {
        return Tournament::factory()->create(array_merge([
            'arbiter_id' => $arbiter->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addParticipant(
        Tournament $tournament,
        Player $player,
        array $overrides = []
    ): TournamentParticipant {
        return $tournament->participants()->create(array_merge([
            'player_id' => $player->id,
            'points' => 0,
            'rating_before' => $player->current_rating,
            'version' => 1,
        ], $overrides));
    }

    private function startTournament(User $arbiter, Tournament $tournament): void
    {
        $this->actingAs($arbiter)
            ->post("/tournaments/{$tournament->id}/start", [
                'version' => $tournament->version,
            ])
            ->assertRedirect(route('tournaments.show', $tournament));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Открытый турнир',
            'pairing_type' => PairingType::Swiss->value,
            'rating_mode' => RatingMode::Average->value,
            'rounds_count' => 5,
            'games_per_round' => 1,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'location' => 'Шахматный клуб',
            'notes' => 'Регламент турнира',
        ], $overrides);
    }
}
