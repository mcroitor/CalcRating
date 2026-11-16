<?php

namespace Tests\Feature\Clubs;

use App\Enums\ClubStaffRole;
use App\Enums\ClubStatus;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClubCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_clubs_index_and_show_with_search_and_status_filter(): void
    {
        $active = Club::factory()->create(['name' => 'Royal Chess Club', 'code' => 'RCC', 'city' => 'Chisinau']);
        $archived = Club::factory()->archived()->create(['name' => 'Old Guild', 'code' => 'OLD', 'city' => 'Balti']);

        $this->get(route('clubs.index'))
            ->assertOk()
            ->assertSee('Royal Chess Club')
            ->assertDontSee('Old Guild');

        $this->get(route('clubs.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Old Guild')
            ->assertDontSee('Royal Chess Club');

        $this->get(route('clubs.index', ['status' => 'all', 'search' => 'RCC']))
            ->assertOk()
            ->assertSee('Royal Chess Club')
            ->assertDontSee('Old Guild');

        $this->get(route('clubs.show', $active))
            ->assertOk()
            ->assertSee('Royal Chess Club')
            ->assertSee('Chisinau');
    }

    public function test_only_admin_can_create_club_and_upload_raster_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $manager = User::factory()->arbiter()->create();
        $guest = User::factory()->guest()->create();

        $this->actingAs($guest)->get(route('clubs.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('clubs.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('clubs.store'), ['name' => 'Blocked'])->assertForbidden();

        // SVG must be rejected (stored XSS protection)
        $svg = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');
        $this->actingAs($admin)
            ->post(route('clubs.store'), [
                'name' => 'Bad Logo Club',
                'manager_id' => $manager->id,
                'logo' => $svg,
            ])
            ->assertSessionHasErrors('logo');

        $logo = UploadedFile::fake()->image('club-logo.png', 200, 200);
        $response = $this->actingAs($admin)->post(route('clubs.store'), [
            'name' => 'Grandmaster Club',
            'code' => 'gmc',
            'city' => 'Chisinau',
            'website' => 'https://gmc.example.com',
            'manager_id' => $manager->id,
            'logo' => $logo,
        ]);

        $club = Club::query()->where('name', 'Grandmaster Club')->firstOrFail();
        $response->assertRedirect(route('clubs.show', $club));

        $this->assertSame('GMC', $club->code);
        $this->assertNotNull($club->logo_path);
        Storage::disk('public')->assertExists($club->logo_path);
        $this->assertTrue($club->hasManagerUser($manager));
    }

    public function test_club_manager_can_update_club_and_replace_or_remove_logo_with_optimistic_locking(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $manager = User::factory()->arbiter()->create();
        $otherArbiter = User::factory()->arbiter()->create();

        $oldLogo = UploadedFile::fake()->image('old.jpg', 100, 100)->store('clubs', 'public');
        $club = Club::factory()->create([
            'name' => 'Initial Club',
            'logo_path' => $oldLogo,
            'version' => 1,
        ]);
        $club->staff()->attach($manager->id, ['role' => ClubStaffRole::Manager->value]);

        // Another arbiter cannot edit this club
        $this->actingAs($otherArbiter)->get(route('clubs.edit', $club))->assertForbidden();
        $this->actingAs($otherArbiter)->put(route('clubs.update', $club), [
            'name' => 'Hijacked',
            'version' => 1,
        ])->assertForbidden();

        // Manager replaces logo
        $newLogo = UploadedFile::fake()->image('new.png', 150, 150);
        $this->actingAs($manager)
            ->put(route('clubs.update', $club), [
                'name' => 'Updated Club',
                'code' => 'UPD',
                'version' => 1,
                'logo' => $newLogo,
            ])
            ->assertRedirect(route('clubs.show', $club));

        $club->refresh();
        $this->assertSame('Updated Club', $club->name);
        $this->assertSame(2, $club->version);
        $this->assertNotSame($oldLogo, $club->logo_path);
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertExists($club->logo_path);

        // Stale version returns 409
        $this->actingAs($manager)
            ->put(route('clubs.update', $club), [
                'name' => 'Stale Update',
                'version' => 1,
            ])
            ->assertStatus(409);

        // Admin removes logo
        $currentLogo = $club->logo_path;
        $this->actingAs($admin)
            ->put(route('clubs.update', $club), [
                'name' => 'Updated Club',
                'version' => $club->version,
                'remove_logo' => 1,
            ])
            ->assertRedirect(route('clubs.show', $club));

        $club->refresh();
        $this->assertNull($club->logo_path);
        Storage::disk('public')->assertMissing($currentLogo);
    }

    public function test_club_with_tournaments_cannot_be_deleted_and_must_be_archived_instead(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $manager = User::factory()->arbiter()->create();
        $logoPath = UploadedFile::fake()->image('logo.webp', 80, 80)->store('clubs', 'public');

        $clubWithTournament = Club::factory()->create(['logo_path' => $logoPath]);
        $clubWithTournament->staff()->attach($manager->id, ['role' => ClubStaffRole::Manager->value]);
        Tournament::factory()->create(['club_id' => $clubWithTournament->id, 'arbiter_id' => $manager->id]);

        // Cannot delete club with tournament
        $this->actingAs($admin)
            ->delete(route('clubs.destroy', $clubWithTournament))
            ->assertRedirect(route('clubs.show', $clubWithTournament))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('clubs', ['id' => $clubWithTournament->id]);

        // Archive and restore work
        $this->actingAs($admin)
            ->post(route('clubs.archive', $clubWithTournament), ['version' => $clubWithTournament->version])
            ->assertRedirect(route('clubs.show', $clubWithTournament));

        $this->assertSame(ClubStatus::Archived, $clubWithTournament->fresh()->status);

        $this->actingAs($admin)
            ->post(route('clubs.restore', $clubWithTournament), ['version' => $clubWithTournament->fresh()->version])
            ->assertRedirect(route('clubs.show', $clubWithTournament));

        $this->assertSame(ClubStatus::Active, $clubWithTournament->fresh()->status);

        // Empty club can be deleted along with its logo
        $emptyLogo = UploadedFile::fake()->image('empty.png', 80, 80)->store('clubs', 'public');
        $emptyClub = Club::factory()->create(['logo_path' => $emptyLogo]);

        $this->actingAs($admin)
            ->delete(route('clubs.destroy', $emptyClub))
            ->assertRedirect(route('clubs.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('clubs', ['id' => $emptyClub->id]);
        Storage::disk('public')->assertMissing($emptyLogo);
    }
}
