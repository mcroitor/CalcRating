<?php

namespace Tests\Feature\Clubs;

use App\Enums\ClubStaffRole;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubStaffAndMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_arbiter_but_only_admin_can_manage_managers_and_not_remove_last_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->arbiter()->create();
        $arbiter = User::factory()->arbiter()->create();
        $guest = User::factory()->guest()->create();
        $blockedArbiter = User::factory()->arbiter()->blocked()->create();

        $club = Club::factory()->create();
        $club->staff()->attach($manager->id, ['role' => ClubStaffRole::Manager->value]);

        // Club manager can add an active arbiter as club arbiter
        $this->actingAs($manager)
            ->post(route('clubs.staff.store', $club), [
                'user_id' => $arbiter->id,
                'role' => ClubStaffRole::Arbiter->value,
            ])
            ->assertSessionHas('success');

        $this->assertTrue($club->hasStaffUser($arbiter));

        // Club manager cannot assign another manager (admin only)
        $this->actingAs($manager)
            ->post(route('clubs.staff.store', $club), [
                'user_id' => $arbiter->id,
                'role' => ClubStaffRole::Manager->value,
            ])
            ->assertForbidden();

        // Guest and blocked users cannot be assigned to club staff
        $this->actingAs($admin)
            ->post(route('clubs.staff.store', $club), [
                'user_id' => $guest->id,
                'role' => ClubStaffRole::Arbiter->value,
            ])
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->post(route('clubs.staff.store', $club), [
                'user_id' => $blockedArbiter->id,
                'role' => ClubStaffRole::Arbiter->value,
            ])
            ->assertSessionHas('error');

        // Last active manager cannot be removed or demoted
        $this->actingAs($admin)
            ->delete(route('clubs.staff.destroy', [$club, $manager]))
            ->assertSessionHas('error');

        $this->assertTrue($club->hasManagerUser($manager));

        $this->actingAs($admin)
            ->post(route('clubs.staff.store', $club), [
                'user_id' => $manager->id,
                'role' => ClubStaffRole::Arbiter->value,
            ])
            ->assertSessionHas('error');

        $this->assertTrue($club->hasManagerUser($manager));

        // Once a second manager is added, the first can be removed
        $this->actingAs($admin)
            ->post(route('clubs.staff.store', $club), [
                'user_id' => $arbiter->id,
                'role' => ClubStaffRole::Manager->value,
            ])
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->delete(route('clubs.staff.destroy', [$club, $manager]))
            ->assertSessionHas('success');

        $this->assertFalse($club->hasStaffUser($manager));
    }

    public function test_players_can_belong_to_zero_one_or_multiple_clubs_and_filter_works(): void
    {
        $managerA = User::factory()->arbiter()->create();
        $managerB = User::factory()->arbiter()->create();

        $clubA = Club::factory()->create(['name' => 'Alpha Club']);
        $clubB = Club::factory()->create(['name' => 'Beta Club']);
        $clubA->staff()->attach($managerA->id, ['role' => ClubStaffRole::Manager->value]);
        $clubB->staff()->attach($managerB->id, ['role' => ClubStaffRole::Manager->value]);

        $multiClubPlayer = Player::factory()->create(['first_name' => 'Multi', 'last_name' => 'Player']);
        $freeAgent = Player::factory()->create(['first_name' => 'Free', 'last_name' => 'Agent']);

        // Manager A adds player to Club A, Manager B adds same player to Club B
        $this->actingAs($managerA)
            ->post(route('clubs.members.store', $clubA), ['player_id' => $multiClubPlayer->id])
            ->assertSessionHas('success');

        $this->actingAs($managerB)
            ->post(route('clubs.members.store', $clubB), ['player_id' => $multiClubPlayer->id])
            ->assertSessionHas('success');

        $this->assertCount(2, $multiClubPlayer->fresh()->clubs);
        $this->assertCount(0, $freeAgent->fresh()->clubs);

        // Player card shows both clubs
        $this->get(route('players.show', $multiClubPlayer))
            ->assertOk()
            ->assertSee('Alpha Club')
            ->assertSee('Beta Club');

        // Players filter by club and "no club"
        $this->get(route('players.index', ['club_id' => $clubA->id]))
            ->assertOk()
            ->assertSee('Player Multi')
            ->assertDontSee('Agent Free');

        $this->get(route('players.index', ['club_id' => 'none']))
            ->assertOk()
            ->assertSee('Agent Free')
            ->assertDontSee('Player Multi');

        // Manager A cannot remove member from Club B
        $this->actingAs($managerA)
            ->delete(route('clubs.members.destroy', [$clubB, $multiClubPlayer]))
            ->assertForbidden();

        // Manager A can remove member from Club A
        $this->actingAs($managerA)
            ->delete(route('clubs.members.destroy', [$clubA, $multiClubPlayer]))
            ->assertSessionHas('success');

        $this->assertCount(1, $multiClubPlayer->fresh()->clubs);
    }

    public function test_admin_receives_warning_when_blocking_sole_active_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $soleManager = User::factory()->arbiter()->create(['version' => 1]);
        $club = Club::factory()->create(['name' => 'Orphan Risk Club']);
        $club->staff()->attach($soleManager->id, ['role' => ClubStaffRole::Manager->value]);

        $this->actingAs($admin)
            ->post(route('admin.users.block', $soleManager), [
                'version' => $soleManager->version,
                'reason' => 'Suspended',
            ])
            ->assertSessionHas('success')
            ->assertSessionHas('warning');
    }
}
