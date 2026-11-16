<?php

namespace Tests\Feature\Clubs;

use App\Enums\ClubStaffRole;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Models\Club;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ClubTournamentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_requires_active_club_and_isolates_access_between_clubs(): void
    {
        $managerA = User::factory()->arbiter()->create();
        $arbiterA = User::factory()->arbiter()->create();
        $arbiterB = User::factory()->arbiter()->create();

        $clubA = Club::factory()->create(['name' => 'Club Alpha']);
        $clubA->staff()->attach($managerA->id, ['role' => ClubStaffRole::Manager->value]);
        $clubA->staff()->attach($arbiterA->id, ['role' => ClubStaffRole::Arbiter->value]);

        $clubB = Club::factory()->create(['name' => 'Club Beta']);
        $clubB->staff()->attach($arbiterB->id, ['role' => ClubStaffRole::Arbiter->value]);

        $archivedClub = Club::factory()->archived()->create(['name' => 'Archived Club']);
        $archivedClub->staff()->attach($managerA->id, ['role' => ClubStaffRole::Manager->value]);

        // Plain club arbiter (not manager) cannot create tournaments
        $this->actingAs($arbiterA)->get(route('tournaments.create'))->assertForbidden();

        // Manager A cannot create tournament without club_id or in archived club or in Club B
        $this->actingAs($managerA)->post(route('tournaments.store'), [
            'name' => 'Missing Club',
            'pairing_type' => PairingType::Swiss->value,
            'rounds_count' => 3,
            'games_per_round' => 1,
            'start_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('club_id');

        $this->actingAs($managerA)->post(route('tournaments.store'), [
            'club_id' => $archivedClub->id,
            'name' => 'In Archived',
            'pairing_type' => PairingType::Swiss->value,
            'rounds_count' => 3,
            'games_per_round' => 1,
            'start_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('club_id');

        $this->actingAs($managerA)->post(route('tournaments.store'), [
            'club_id' => $clubB->id,
            'name' => 'Foreign Club',
            'pairing_type' => PairingType::Swiss->value,
            'rounds_count' => 3,
            'games_per_round' => 1,
            'start_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('club_id');

        // Manager A creates tournament in Club A and assigns Arbiter A
        $this->actingAs($managerA)->post(route('tournaments.store'), [
            'club_id' => $clubA->id,
            'arbiter_id' => $arbiterA->id,
            'name' => 'Alpha Open',
            'pairing_type' => PairingType::Swiss->value,
            'rating_mode' => RatingMode::Average->value,
            'rounds_count' => 3,
            'games_per_round' => 1,
            'start_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $tournament = Tournament::query()->where('name', 'Alpha Open')->firstOrFail();

        // Arbiter B (foreign club) gets 403 on edit, participants, start, export
        $this->actingAs($arbiterB)->get(route('tournaments.edit', $tournament))->assertForbidden();
        $this->actingAs($arbiterB)->post(route('tournaments.participants.store', $tournament), [])->assertForbidden();
        $this->actingAs($arbiterB)->post(route('tournaments.start', $tournament), ['version' => $tournament->version])->assertForbidden();
        $this->actingAs($arbiterB)->get(route('tournaments.export', [$tournament, 'html']))->assertForbidden();

        // Assigned Arbiter A and Manager A both have access
        $this->actingAs($arbiterA)->get(route('tournaments.edit', $tournament))->assertOk();
        $this->actingAs($managerA)->get(route('tournaments.edit', $tournament))->assertOk();
    }

    public function test_open_tournament_allows_non_members_and_supports_club_members_filter(): void
    {
        $manager = User::factory()->arbiter()->create();
        $club = Club::factory()->create(['name' => 'Home Club']);
        $club->staff()->attach($manager->id, ['role' => ClubStaffRole::Manager->value]);

        $member = Player::factory()->create(['first_name' => 'Club', 'last_name' => 'Member']);
        $outsider = Player::factory()->create(['first_name' => 'Outside', 'last_name' => 'Guest']);
        $club->members()->attach($member->id, ['joined_at' => now()->toDateString()]);

        $tournament = Tournament::factory()->create([
            'club_id' => $club->id,
            'arbiter_id' => $manager->id,
        ]);

        // Filter club_members_only=1 shows only club members
        $this->actingAs($manager)
            ->get(route('tournaments.show', [$tournament, 'club_members_only' => 1]))
            ->assertOk()
            ->assertSee('Member Club')
            ->assertDontSee('Guest Outside');

        // Outsider can still be added to the club tournament
        $this->actingAs($manager)
            ->post(route('tournaments.participants.store', $tournament), [
                'player_id' => $outsider->id,
                'version' => $tournament->version,
            ])
            ->assertRedirect(route('tournaments.show', $tournament));

        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament->id,
            'player_id' => $outsider->id,
        ]);
    }

    public function test_player_import_assigns_new_and_existing_players_to_selected_club_and_blocks_foreign_club(): void
    {
        $manager = User::factory()->arbiter()->create();
        $myClub = Club::factory()->create(['name' => 'Import Club']);
        $myClub->staff()->attach($manager->id, ['role' => ClubStaffRole::Manager->value]);
        $foreignClub = Club::factory()->create(['name' => 'Foreign Club']);

        $existing = Player::factory()->create([
            'first_name' => 'Existing',
            'last_name' => 'Player',
            'birth_date' => '1990-01-15',
        ]);

        $csv = "first_name,last_name,birth_date,current_rating\n"
            ."Existing,Player,1990-01-15,1500\n"
            ."Fresh,Import,1995-05-20,1600\n";

        // Foreign club rejected with 403
        $this->actingAs($manager)
            ->post(route('players.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('players.csv', $csv),
                'club_id' => $foreignClub->id,
            ])
            ->assertForbidden();

        // Own club attaches both the newly created player and the deduplicated existing player
        $this->actingAs($manager)
            ->post(route('players.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('players.csv', $csv),
                'club_id' => $myClub->id,
            ])
            ->assertRedirect(route('players.import'));

        $this->assertSame(1, Player::query()->where('first_name', 'Existing')->where('last_name', 'Player')->count());
        $fresh = Player::query()->where('first_name', 'Fresh')->where('last_name', 'Import')->firstOrFail();

        $this->assertTrue($myClub->members()->where('players.id', $existing->id)->exists());
        $this->assertTrue($myClub->members()->where('players.id', $fresh->id)->exists());
    }

    public function test_tournament_exports_include_club_in_html_pdf_and_xlsx(): void
    {
        $manager = User::factory()->arbiter()->create();
        $club = Club::factory()->create(['name' => 'Export Chess Club']);
        $club->staff()->attach($manager->id, ['role' => ClubStaffRole::Manager->value]);

        $tournament = Tournament::factory()->create([
            'club_id' => $club->id,
            'arbiter_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('tournaments.export', [$tournament, 'html']))
            ->assertOk()
            ->assertSee('Export Chess Club');

        $this->actingAs($manager)
            ->get(route('tournaments.export', [$tournament, 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $xlsx = $this->actingAs($manager)->get(route('tournaments.export', [$tournament, 'xlsx']));
        $xlsx->assertOk();

        $baseResponse = $xlsx->baseResponse;
        $this->assertInstanceOf(StreamedResponse::class, $baseResponse);

        ob_start();
        $baseResponse->sendContent();
        $binary = ob_get_clean();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_club_');
        file_put_contents($tmp, $binary);

        try {
            $sheet = IOFactory::load($tmp)->getSheet(0)->toArray();
            $clubRow = collect($sheet)->first(fn ($row) => ($row[0] ?? null) === 'club');
            $this->assertNotNull($clubRow);
            $this->assertSame('Export Chess Club', $clubRow[1]);
        } finally {
            @unlink($tmp);
        }
    }
}
