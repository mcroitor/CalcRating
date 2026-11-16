<?php

namespace Tests\Unit;

use App\Enums\ChessTitle;
use App\Models\Player;
use App\Models\Rating;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_creation_automatically_creates_initial_rating(): void
    {
        $player = Player::create([
            'first_name' => 'Гарри',
            'last_name' => 'Каспаров',
            'birth_date' => '1963-04-13',
            'title' => ChessTitle::GM,
            'current_rating' => 2800,
        ]);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'current_rating' => 2800,
        ]);

        $this->assertDatabaseHas('ratings', [
            'player_id' => $player->id,
            'tournament_id' => null,
            'value' => 2800,
        ]);

        $this->assertCount(1, $player->ratings);
        $this->assertSame(2800, $player->ratings->first()->value);
        $this->assertNull($player->ratings->first()->tournament_id);
    }

    public function test_player_full_name_attribute(): void
    {
        $player = new Player([
            'first_name' => 'Анатолий',
            'last_name' => 'Карпов',
        ]);

        $this->assertSame('Карпов Анатолий', $player->full_name);
    }

    public function test_player_age_calculation(): void
    {
        $birthDate = Carbon::now()->subYears(25)->subMonths(2)->format('Y-m-d');
        $player = new Player([
            'birth_date' => $birthDate,
        ]);

        $this->assertSame(25, $player->age);
    }

    public function test_player_has_tournament_history_detection(): void
    {
        $player = Player::create([
            'first_name' => 'Магнус',
            'last_name' => 'Карлсен',
            'birth_date' => '1990-11-30',
            'title' => ChessTitle::GM,
            'current_rating' => 2850,
        ]);

        // Only initial rating: no tournament history
        $this->assertFalse($player->hasTournamentRatingHistory());

        // Add a tournament rating
        $tournament = Tournament::factory()->create();

        Rating::create([
            'player_id' => $player->id,
            'tournament_id' => $tournament->id,
            'value' => 2860,
            'created_at' => now(),
        ]);

        $player->refresh();
        $this->assertTrue($player->hasTournamentRatingHistory());
    }
}
