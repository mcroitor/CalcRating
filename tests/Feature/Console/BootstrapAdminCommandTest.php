<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class BootstrapAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_initial_admin_with_interactive_password_and_writes_audit_log(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('audit')->andReturnSelf();

        $this->artisan('admin:bootstrap', [
            'email' => 'Root@Club.Example',
            '--name' => 'System',
            '--surname' => 'Owner',
        ])
            ->expectsQuestion('Password', 'StrongPass123!')
            ->expectsQuestion('Confirm password', 'StrongPass123!')
            ->expectsOutputToContain('root@club.example created as administrator.')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'root@club.example')->first();

        $this->assertNotNull($admin);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('System', $admin->name);
        $this->assertSame('Owner', $admin->surname);
        $this->assertFalse($admin->is_blocked);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame(1, $admin->version);
        $this->assertTrue(Hash::check('StrongPass123!', $admin->password));

        Log::shouldHaveReceived('info')
            ->with('admin.bootstrapped', Mockery::type('array'))
            ->once();
    }

    public function test_creates_admin_with_send_reset_flag_without_prompting_for_password(): void
    {
        Notification::fake();

        $this->artisan('admin:bootstrap', [
            'email' => 'admin@club.example',
            '--send-reset' => true,
        ])
            ->expectsOutputToContain('admin@club.example created as administrator.')
            ->expectsOutputToContain('Password reset link sent.')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@club.example')->first();

        $this->assertNotNull($admin);
        $this->assertSame(UserRole::Admin, $admin->role);
        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_fails_when_user_exists_without_promote_flag(): void
    {
        $guest = User::factory()->guest()->create([
            'email' => 'existing@club.example',
        ]);

        $this->artisan('admin:bootstrap', [
            'email' => 'existing@club.example',
        ])
            ->expectsOutputToContain('--promote')
            ->assertFailed();

        $this->assertSame(UserRole::Guest, $guest->fresh()->role);
    }

    public function test_promotes_and_unblocks_existing_user_with_optimistic_locking(): void
    {
        $blockedGuest = User::factory()->guest()->blocked()->create([
            'email' => 'member@club.example',
            'password' => Hash::make('OriginalPass123!'),
            'version' => 3,
        ]);

        $this->artisan('admin:bootstrap', [
            'email' => 'member@club.example',
            '--promote' => true,
        ])
            ->expectsOutputToContain('member@club.example promoted to administrator.')
            ->assertSuccessful();

        $promoted = $blockedGuest->fresh();

        $this->assertSame(UserRole::Admin, $promoted->role);
        $this->assertFalse($promoted->is_blocked);
        $this->assertNull($promoted->blocked_at);
        $this->assertNull($promoted->blocked_reason);
        $this->assertSame(4, $promoted->version);
        $this->assertTrue(Hash::check('OriginalPass123!', $promoted->password));
    }

    public function test_rejects_mismatched_password_confirmation_and_invalid_email(): void
    {
        $this->artisan('admin:bootstrap', [
            'email' => 'not-an-email',
        ])->assertFailed();

        $this->artisan('admin:bootstrap', [
            'email' => 'valid@club.example',
        ])
            ->expectsQuestion('Password', 'StrongPass123!')
            ->expectsQuestion('Confirm password', 'DifferentPass123!')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'valid@club.example']);
    }
}
