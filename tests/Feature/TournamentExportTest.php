<?php

namespace Tests\Feature;

use App\Enums\ChessTitle;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class TournamentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_403_on_export_url(): void
    {
        $tournament = Tournament::factory()->create();
        $response = $this->get(route('tournaments.export', [$tournament, 'pdf']));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_role_gets_403_on_export_url(): void
    {
        $guest = User::factory()->guest()->create();
        $tournament = Tournament::factory()->create();
        $response = $this->actingAs($guest)->get(route('tournaments.export', [$tournament, 'pdf']));
        $response->assertForbidden();
    }

    public function test_export_links_are_visible_on_tournament_page_only_for_arbiters(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = Tournament::factory()->create(['arbiter_id' => $arbiter->id]);

        $this->actingAs($arbiter)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee(route('tournaments.export', [$tournament, 'xlsx']), false)
            ->assertSee(route('tournaments.export', [$tournament, 'pdf']), false)
            ->assertSee(route('tournaments.export', [$tournament, 'html']), false);

        $guest = User::factory()->guest()->create();

        $this->actingAs($guest)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertDontSee(route('tournaments.export', [$tournament, 'xlsx']), false);

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertDontSee(route('tournaments.export', [$tournament, 'pdf']), false);
    }

    public function test_arbiter_can_download_pdf_and_xlsx_and_open_html(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->readyTournament($arbiter, RatingMode::Average);

        $pdf = $this->actingAs($arbiter)->get(route('tournaments.export', [$tournament, 'pdf']));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $xlsx = $this->actingAs($arbiter)->get(route('tournaments.export', [$tournament, 'xlsx']));
        $xlsx->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $html = $this->actingAs($arbiter)->get(route('tournaments.export', [$tournament, 'html']));
        $html->assertOk();
        $html->assertSee($tournament->name);
        $html->assertSee($arbiter->surname);
        $html->assertSee(RatingMode::Average->label());
    }

    public function test_unknown_export_format_returns_404(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = $this->readyTournament($arbiter, RatingMode::Average);

        $this->actingAs($arbiter)
            ->get(route('tournaments.export', [$tournament, 'csv']))
            ->assertNotFound();
    }

    public function test_xlsx_contains_tournament_header_standings_and_rounds(): void
    {
        [$tournament, $arbiter] = $this->completedTournament();

        $spreadsheet = $this->downloadXlsx($arbiter, $tournament);

        $this->assertSame(['Tournament', 'Round 1', 'Round 2'], $spreadsheet->getSheetNames());

        $rows = $this->sheetRows($spreadsheet, 0);

        $this->assertSame($tournament->name, $this->valueFor($rows, 'name'));
        $this->assertSame('2026-03-01', $this->valueFor($rows, 'start_date'));
        $this->assertSame('2026-03-03', $this->valueFor($rows, 'end_date'));
        $this->assertSame('Кишинёв', $this->valueFor($rows, 'location'));
        $this->assertSame(trim($arbiter->surname.' '.$arbiter->name), $this->valueFor($rows, 'arbiter'));
        $this->assertSame('round_robin', $this->valueFor($rows, 'pairing_type'));
        $this->assertSame('per_game', $this->valueFor($rows, 'rating_mode'));

        $header = $this->headerRowIndex($rows, ['place', 'player', 'title', 'rating_before', 'points', 'rating_after', 'delta']);

        $first = $rows[$header + 1];
        $this->assertSame(1, (int) $first[0]);
        $this->assertSame('Петров Иван', $first[1]);
        $this->assertSame('GM', $first[2]);
        $this->assertSame(2400, (int) $first[3]);
        $this->assertSame(1.5, (float) $first[4]);
        $this->assertSame(2410, (int) $first[5]);
        $this->assertSame(10, (int) $first[6]);

        $second = $rows[$header + 2];
        $this->assertSame(2, (int) $second[0]);
        $this->assertSame('Сидорова Анна', $second[1]);
        $this->assertSame('IM', $second[2]);
        $this->assertSame(2300, (int) $second[3]);
        $this->assertSame(0.5, (float) $second[4]);
        $this->assertSame(2290, (int) $second[5]);
        $this->assertSame(-10, (int) $second[6]);

        $roundOne = $this->sheetRows($spreadsheet, 1);
        $this->assertSame(['board_number', 'white', 'result', 'black', 'advanced'], $roundOne[0]);
        $this->assertSame('Петров Иван', $roundOne[1][1]);
        $this->assertSame('1-0', $roundOne[1][2]);
        $this->assertSame('Сидорова Анна', $roundOne[1][3]);

        $roundTwo = $this->sheetRows($spreadsheet, 2);
        $this->assertSame('1/2-1/2', $roundTwo[1][2]);
        $this->assertSame('Петров Иван', $roundTwo[1][4]);
    }

    public function test_html_export_contains_title_system_and_half_points(): void
    {
        [$tournament, $arbiter] = $this->completedTournament();

        $response = $this->actingAs($arbiter)->get(route('tournaments.export', [$tournament, 'html']));

        $response->assertOk();
        $response->assertSee(__('tournaments.export.col_title'));
        $response->assertSee(ChessTitle::GM->label());
        $response->assertSee(ChessTitle::IM->label());
        $response->assertSee('Петров Иван');
        $response->assertSee('1.5');
        $response->assertSee('0.5');
        $response->assertSee('1/2-1/2');
        $response->assertSee(PairingType::RoundRobin->label());
        $response->assertSee(RatingMode::PerGame->label());
        $response->assertSee('window.print()');
    }

    public function test_export_places_follow_tiebreak_chain(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $tournament = Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'status' => TournamentStatus::Completed,
            'pairing_type' => PairingType::Swiss,
        ]);

        [$first, $second, $third, $fourth] = $this->participants($tournament, [
            ['Первый Игрок', 2100, 1.0],
            ['Второй Игрок', 2050, 1.0],
            ['Третий Игрок', 2200, 1.0],
            ['Четвёртый Игрок', 1900, 0.0],
        ]);

        // Wins break the three-way tie: first two have a win, third has none.
        $this->pairing($tournament, 1, 1, $first, $third, '1-0');
        $this->pairing($tournament, 1, 2, $second, $fourth, '1-0');

        $html = $this->actingAs($arbiter)->get(route('tournaments.export', [$tournament, 'html']));
        $html->assertOk();
        $html->assertSeeInOrder(['Первый Игрок', 'Второй Игрок', 'Третий Игрок', 'Четвёртый Игрок']);

        $spreadsheet = $this->downloadXlsx($arbiter, $tournament);
        $rows = $this->sheetRows($spreadsheet, 0);
        $header = $this->headerRowIndex($rows, ['place', 'player', 'title', 'rating_before', 'points', 'rating_after', 'delta']);

        $this->assertSame('Первый Игрок', $rows[$header + 1][1]);
        $this->assertSame('Второй Игрок', $rows[$header + 2][1]);
        $this->assertSame('Третий Игрок', $rows[$header + 3][1]);
        $this->assertSame('Четвёртый Игрок', $rows[$header + 4][1]);
    }

    public function test_xlsx_escapes_formula_injection_in_player_name(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'status' => TournamentStatus::InProgress,
        ]);

        $player = Player::factory()->create([
            'first_name' => 'Иван',
            'last_name' => '=SUM(A1:A2)',
            'current_rating' => 2000,
        ]);

        $tournament->participants()->create([
            'player_id' => $player->id,
            'points' => 0,
            'rating_before' => 2000,
            'version' => 1,
        ]);

        $spreadsheet = $this->downloadXlsx($arbiter, $tournament);
        $rows = $this->sheetRows($spreadsheet, 0);
        $header = $this->headerRowIndex($rows, ['place', 'player', 'title', 'rating_before', 'points', 'rating_after', 'delta']);

        $this->assertStringStartsWith("'=SUM", (string) $rows[$header + 1][1]);
    }

    public function test_draft_tournament_export_shows_missing_ratings(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'status' => TournamentStatus::Draft,
        ]);

        $this->participants($tournament, [['Черновик Игрок', 1500, 0.0]]);

        $html = $this->actingAs($arbiter)->get(route('tournaments.export', [$tournament, 'html']));
        $html->assertOk();
        $html->assertSee('—');

        $spreadsheet = $this->downloadXlsx($arbiter, $tournament);
        $rows = $this->sheetRows($spreadsheet, 0);
        $header = $this->headerRowIndex($rows, ['place', 'player', 'title', 'rating_before', 'points', 'rating_after', 'delta']);

        $this->assertTrue(in_array($rows[$header + 1][5], [null, ''], true));
        $this->assertTrue(in_array($rows[$header + 1][6], [null, ''], true));
    }

    public function test_pdf_generation_is_fast_for_large_tournament(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $tournament = Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'status' => TournamentStatus::InProgress,
            'pairing_type' => PairingType::Swiss,
            'rounds_count' => 5,
        ]);

        $players = [];
        for ($i = 1; $i <= 32; $i++) {
            $players[] = Player::factory()->create([
                'first_name' => "Игрок{$i}",
                'last_name' => 'Тестов',
                'current_rating' => 2400 - $i,
            ]);
        }

        foreach ($players as $index => $player) {
            $tournament->participants()->create([
                'player_id' => $player->id,
                'points' => 0,
                'rating_before' => $player->current_rating,
                'version' => 1,
            ]);
        }

        for ($round = 1; $round <= 5; $round++) {
            for ($board = 1; $board <= 16; $board++) {
                $white = $players[($board - 1) * 2];
                $black = $players[($board - 1) * 2 + 1];

                $this->pairing($tournament, $round, $board, $white, $black, '1-0');
            }
        }

        $started = hrtime(true);
        $response = $this->actingAs($arbiter)->get(route('tournaments.export', [$tournament->fresh(), 'pdf']));
        $elapsed = (hrtime(true) - $started) / 1_000_000_000;

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertLessThan(2.0, $elapsed, sprintf('PDF took %.2f s', $elapsed));
    }

    public function test_pdf_template_renders_russian_labels_and_half_points(): void
    {
        [$tournament] = $this->completedTournament();
        $tournament = $tournament->fresh();

        app()->setLocale('ru');

        $html = view('tournaments.export-pdf', [
            'tournament' => $tournament,
            'standings' => app(StandingsService::class)->build($tournament),
            'pairingsByRound' => $tournament->pairings->groupBy('round_number'),
            'generated_at' => now()->format('d.m.Y H:i'),
        ])->render();

        $this->assertStringContainsString('Итоговая таблица', $html);
        $this->assertStringContainsString('Гроссмейстер (GM)', $html);
        $this->assertStringContainsString('1.5', $html);
        $this->assertStringContainsString('1/2-1/2', $html);
        $this->assertStringNotContainsString("\u{FFFD}", $html);
    }

    /**
     * @return array{0: Tournament, 1: User}
     */
    private function completedTournament(): array
    {
        $arbiter = User::factory()->arbiter()->create([
            'name' => 'Пётр',
            'surname' => 'Судьин',
        ]);

        $tournament = Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'name' => 'Открытый кубок',
            'location' => 'Кишинёв',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-03',
            'pairing_type' => PairingType::RoundRobin,
            'rating_mode' => RatingMode::PerGame,
            'status' => TournamentStatus::Completed,
        ]);

        $first = Player::factory()->create([
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'title' => ChessTitle::GM,
            'current_rating' => 2410,
        ]);

        $second = Player::factory()->create([
            'first_name' => 'Анна',
            'last_name' => 'Сидорова',
            'title' => ChessTitle::IM,
            'current_rating' => 2290,
        ]);

        foreach ([[$first, 1.5, 2400, 2410], [$second, 0.5, 2300, 2290]] as [$player, $points, $before, $after]) {
            $tournament->participants()->create([
                'player_id' => $player->id,
                'points' => $points,
                'rating_before' => $before,
                'rating_after' => $after,
                'version' => 1,
            ]);
        }

        $this->pairing($tournament, 1, 1, $first, $second, '1-0');
        $draw = $this->pairing($tournament, 2, 1, $second, $first, '1/2-1/2');
        $draw->update(['advanced_player_id' => $first->id]);

        return [$tournament->fresh(), $arbiter];
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: float}>  $rows
     * @return list<Player>
     */
    private function participants(Tournament $tournament, array $rows): array
    {
        $players = [];

        foreach ($rows as [$name, $rating, $points]) {
            [$lastName, $firstName] = explode(' ', $name, 2);

            $player = Player::factory()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'current_rating' => $rating,
            ]);

            $tournament->participants()->create([
                'player_id' => $player->id,
                'points' => $points,
                'rating_before' => $rating,
                'version' => 1,
            ]);

            $players[] = $player;
        }

        return $players;
    }

    private function pairing(
        Tournament $tournament,
        int $round,
        int $board,
        Player $white,
        Player $black,
        string $result
    ): Pairing {
        return $tournament->pairings()->create([
            'round_number' => $round,
            'board_number' => $board,
            'white_player_id' => $white->id,
            'black_player_id' => $black->id,
            'result' => $result,
            'version' => 1,
        ]);
    }

    private function readyTournament(User $arbiter, RatingMode $mode): Tournament
    {
        $tournament = Tournament::factory()->create([
            'arbiter_id' => $arbiter->id,
            'status' => TournamentStatus::InProgress,
            'rating_mode' => $mode,
            'version' => 1,
        ]);

        $players = [
            Player::factory()->create(['current_rating' => 2000]),
            Player::factory()->create(['current_rating' => 1800]),
        ];

        foreach ($players as $player) {
            $tournament->participants()->create([
                'player_id' => $player->id,
                'points' => 0,
                'rating_before' => $player->current_rating,
                'version' => 1,
            ]);
        }

        return $tournament;
    }

    private function downloadXlsx(User $arbiter, Tournament $tournament): Spreadsheet
    {
        $response = $this->actingAs($arbiter)
            ->get(route('tournaments.export', [$tournament, 'xlsx']));

        $response->assertOk();

        return $this->loadSpreadsheet($response);
    }

    private function loadSpreadsheet(TestResponse $response): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'tournament-export-');
        file_put_contents($path, $response->streamedContent());

        try {
            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function sheetRows(Spreadsheet $spreadsheet, int $index): array
    {
        return $spreadsheet->getSheet($index)->toArray(null, false, false);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function valueFor(array $rows, string $label): mixed
    {
        foreach ($rows as $row) {
            if (($row[0] ?? null) === $label) {
                return $row[1] ?? null;
            }
        }

        $this->fail("Row with label [{$label}] was not found in the export.");
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  list<string>  $headers
     */
    private function headerRowIndex(array $rows, array $headers): int
    {
        foreach ($rows as $index => $row) {
            if (array_slice($row, 0, count($headers)) === $headers) {
                return $index;
            }
        }

        $this->fail('Header row ['.implode(', ', $headers).'] was not found in the export.');
    }
}
