<?php

namespace Tests\Feature;

use App\Enums\ChessTitle;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_players(): void
    {
        $this->get('/players/export/xlsx')->assertRedirect(route('login'));
        $this->get('/players/export/pdf')->assertRedirect(route('login'));
        $this->get('/players/export/html')->assertRedirect(route('login'));
    }

    public function test_user_with_role_guest_receives_403_on_export(): void
    {
        $guest = User::factory()->guest()->create();

        $this->actingAs($guest)->get('/players/export/xlsx')->assertStatus(403);
        $this->actingAs($guest)->get('/players/export/pdf')->assertStatus(403);
        $this->actingAs($guest)->get('/players/export/html')->assertStatus(403);
    }

    public function test_arbiter_can_export_html(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        Player::factory()->create([
            'first_name' => 'Василий',
            'last_name' => 'Смыслов',
            'current_rating' => 2620,
        ]);

        $response = $this->actingAs($arbiter)->get('/players/export/html');

        $response->assertStatus(200);
        $response->assertSee(__('players.export.title'));
        $response->assertSee('Смыслов Василий');
        $response->assertSee('2620');
        $response->assertSee('window.print()');
    }

    public function test_arbiter_can_export_xlsx(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        Player::factory()->create([
            'first_name' => 'Михаил',
            'last_name' => 'Ботвинник',
            'title' => ChessTitle::GM,
            'current_rating' => 2730,
        ]);

        $response = $this->actingAs($arbiter)->get('/players/export/xlsx');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('players.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_arbiter_can_export_pdf(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        Player::factory()->create([
            'first_name' => 'Роберт',
            'last_name' => 'Фишер',
            'title' => ChessTitle::GM,
            'current_rating' => 2785,
        ]);

        $response = $this->actingAs($arbiter)->get('/players/export/pdf');

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('players.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_export_invalid_format_returns_404(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $response = $this->actingAs($arbiter)->get('/players/export/unknown');
        $response->assertStatus(404);
    }
}
