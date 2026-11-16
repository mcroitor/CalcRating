<?php

namespace Tests\Feature;

use App\Enums\ChessTitle;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_players_by_last_name_and_first_name(): void
    {
        Player::factory()->create([
            'first_name' => 'Роберт',
            'last_name' => 'Фишер',
            'current_rating' => 2785,
        ]);

        Player::factory()->create([
            'first_name' => 'Борис',
            'last_name' => 'Спасский',
            'current_rating' => 2690,
        ]);

        $searchResponse = $this->get('/players?search=Фишер');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Фишер');
        $searchResponse->assertDontSee('Спасский');

        $searchResponseFirst = $this->get('/players?search=Борис');
        $searchResponseFirst->assertStatus(200);
        $searchResponseFirst->assertSee('Спасский');
        $searchResponseFirst->assertDontSee('Фишер');
    }

    public function test_can_filter_players_by_title(): void
    {
        Player::factory()->create([
            'first_name' => 'Гроссмейстер',
            'last_name' => 'Иванов',
            'title' => ChessTitle::GM,
        ]);

        Player::factory()->create([
            'first_name' => 'Новичок',
            'last_name' => 'Петров',
            'title' => ChessTitle::None,
        ]);

        $response = $this->get('/players?title=GM');
        $response->assertStatus(200);
        $response->assertSee('Иванов');
        $response->assertDontSee('Петров');
    }

    public function test_can_filter_players_by_rating_range(): void
    {
        Player::factory()->create([
            'first_name' => 'Сильный',
            'last_name' => 'Игрок',
            'current_rating' => 2500,
        ]);

        Player::factory()->create([
            'first_name' => 'Средний',
            'last_name' => 'Игрок',
            'current_rating' => 1800,
        ]);

        Player::factory()->create([
            'first_name' => 'Начинающий',
            'last_name' => 'Игрок',
            'current_rating' => 1200,
        ]);

        $response = $this->get('/players?min_rating=1500&max_rating=2000');
        $response->assertStatus(200);
        $response->assertSee('Средний');
        $response->assertDontSee('Сильный');
        $response->assertDontSee('Начинающий');
    }
}
