<?php

namespace Tests\Feature;

use App\Enums\ChessTitle;
use App\Models\Player;
use App\Models\Rating;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_players_index_and_show(): void
    {
        $player = Player::factory()->create([
            'first_name' => 'Михаил',
            'last_name' => 'Таль',
            'current_rating' => 2705,
        ]);

        $indexResponse = $this->get('/players');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Таль');

        $showResponse = $this->get("/players/{$player->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Таль Михаил');
        $showResponse->assertSee('2705');
        $showResponse->assertSee(__('players.show.chart_title'));
    }

    public function test_guest_cannot_access_create_or_mutate_players(): void
    {
        $player = Player::factory()->create();

        $this->get('/players/create')->assertRedirect(route('login'));

        $this->post('/players', [
            'first_name' => 'Новый',
            'last_name' => 'Игрок',
            'birth_date' => '2000-01-01',
            'current_rating' => 1200,
        ])->assertRedirect(route('login'));

        $this->get("/players/{$player->id}/edit")->assertRedirect(route('login'));

        $this->put("/players/{$player->id}", [
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'birth_date' => '2000-01-01',
            'version' => 1,
        ])->assertRedirect(route('login'));

        $this->delete("/players/{$player->id}")->assertRedirect(route('login'));
    }

    public function test_user_with_role_guest_receives_403_on_mutation_routes(): void
    {
        $guestUser = User::factory()->guest()->create();
        $player = Player::factory()->create();

        $this->actingAs($guestUser)->get('/players/create')->assertStatus(403);
        $this->actingAs($guestUser)->post('/players', [])->assertStatus(403);
        $this->actingAs($guestUser)->get("/players/{$player->id}/edit")->assertStatus(403);
        $this->actingAs($guestUser)->put("/players/{$player->id}", [])->assertStatus(403);
        $this->actingAs($guestUser)->delete("/players/{$player->id}")->assertStatus(403);
    }

    public function test_arbiter_can_create_player(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $response = $this->actingAs($arbiter)->post('/players', [
            'first_name' => 'Александр',
            'last_name' => 'Алехин',
            'birth_date' => '1892-10-31',
            'title' => 'GM',
            'current_rating' => 2690,
            'notes' => '4-й чемпион мира по шахматам',
        ]);

        $player = Player::where('last_name', 'Алехин')->first();
        $this->assertNotNull($player);
        $this->assertSame('Александр', $player->first_name);
        $this->assertSame(ChessTitle::GM, $player->title);
        $this->assertSame(2690, $player->current_rating);
        $this->assertSame(1, $player->version);

        $response->assertRedirect(route('players.show', $player));

        // Initial rating was recorded
        $this->assertDatabaseHas('ratings', [
            'player_id' => $player->id,
            'value' => 2690,
            'tournament_id' => null,
        ]);
    }

    public function test_player_validation_rules(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        // Birth date in future
        $response1 = $this->actingAs($arbiter)->post('/players', [
            'first_name' => 'Алексей',
            'last_name' => 'Широв',
            'birth_date' => now()->addDay()->format('Y-m-d'),
            'current_rating' => 1500,
        ]);
        $response1->assertSessionHasErrors('birth_date');

        // Negative rating
        $response2 = $this->actingAs($arbiter)->post('/players', [
            'first_name' => 'Алексей',
            'last_name' => 'Широв',
            'birth_date' => '1980-01-01',
            'current_rating' => -100,
        ]);
        $response2->assertSessionHasErrors('current_rating');

        // Missing last name
        $response3 = $this->actingAs($arbiter)->post('/players', [
            'first_name' => 'Алексей',
            'birth_date' => '1980-01-01',
            'current_rating' => 1500,
        ]);
        $response3->assertSessionHasErrors('last_name');
    }

    public function test_arbiter_can_update_player(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $player = Player::factory()->create([
            'first_name' => 'Владимир',
            'last_name' => 'Крамник',
            'version' => 1,
        ]);

        $response = $this->actingAs($arbiter)->put("/players/{$player->id}", [
            'first_name' => 'Владимир',
            'last_name' => 'Крамник-Обновленный',
            'birth_date' => '1975-06-25',
            'title' => 'GM',
            'notes' => '14-й классический чемпион мира',
            'version' => 1,
        ]);

        $response->assertRedirect(route('players.show', $player));

        $player->refresh();
        $this->assertSame('Крамник-Обновленный', $player->last_name);
        $this->assertSame(2, $player->version);
    }

    public function test_player_update_fails_with_409_on_stale_version(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $player = Player::factory()->create([
            'first_name' => 'Вишванатан',
            'last_name' => 'Ананд',
            'version' => 1,
        ]);

        // First concurrent user advances version to 2
        $player->updateWithVersion(['last_name' => 'Ананд-Конкурент'], 1);

        // Second user attempts to update with stale version 1
        $response = $this->actingAs($arbiter)->put("/players/{$player->id}", [
            'first_name' => 'Вишванатан',
            'last_name' => 'Ананд-Опоздавший',
            'birth_date' => '1969-12-11',
            'version' => 1,
        ]);

        $response->assertStatus(409);
        $response->assertSee(__('errors.409.heading'));
        $response->assertSee(__('errors.409.message'));

        $player->refresh();
        $this->assertSame('Ананд-Конкурент', $player->last_name);
        $this->assertSame(2, $player->version);
    }

    public function test_arbiter_can_delete_player_without_tournament_history(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $player = Player::factory()->create([
            'first_name' => 'Удаляемый',
            'last_name' => 'Игрок',
        ]);

        $playerId = $player->id;

        $response = $this->actingAs($arbiter)->delete("/players/{$playerId}");
        $response->assertRedirect(route('players.index'));

        $this->assertDatabaseMissing('players', ['id' => $playerId]);
        $this->assertDatabaseMissing('ratings', ['player_id' => $playerId]);
    }

    public function test_arbiter_cannot_delete_player_with_tournament_history(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $player = Player::factory()->create([
            'first_name' => 'Турнирный',
            'last_name' => 'Игрок',
        ]);

        // Add tournament rating
        $tournament = Tournament::factory()->create();

        Rating::create([
            'player_id' => $player->id,
            'tournament_id' => $tournament->id,
            'value' => 1550,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($arbiter)->delete("/players/{$player->id}");

        // Forbidden by policy because player has tournament history
        $response->assertStatus(403);
        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    public function test_player_in_an_active_tournament_cannot_be_deleted(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $player = Player::factory()->create();
        $tournament = Tournament::factory()->inProgress()->create();

        $tournament->participants()->create([
            'player_id' => $player->id,
            'points' => 0,
            'rating_before' => $player->current_rating,
            'version' => 1,
        ]);

        $response = $this->actingAs($arbiter)->delete("/players/{$player->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }
}
