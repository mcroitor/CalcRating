<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_guest_is_redirected_from_admin_users(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_arbiter_and_guest_receive_403_on_admin_users(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $guest = User::factory()->guest()->create();

        $this->actingAs($arbiter)->get('/admin/users')->assertForbidden();
        $this->actingAs($guest)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_sees_users_with_role_and_status_badges(): void
    {
        $arbiter = User::factory()->arbiter()->create([
            'surname' => 'Иванов',
            'name' => 'Арбитр',
            'email' => 'arbiter-badge@example.com',
        ]);

        $blocked = User::factory()->guest()->blocked()->create([
            'surname' => 'Петров',
            'name' => 'Гость',
            'email' => 'guest-badge@example.com',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee($arbiter->email)
            ->assertSee($blocked->email)
            ->assertSee(UserRole::Arbiter->label())
            ->assertSee(__('admin.index.blocked'))
            ->assertSee(__('admin.index.active'));
    }

    public function test_admin_can_search_and_filter_users(): void
    {
        $arbiter = User::factory()->arbiter()->create([
            'surname' => 'Иванов',
            'name' => 'Пётр',
            'email' => 'arbiter-search@example.com',
        ]);

        $blocked = User::factory()->guest()->blocked()->create([
            'surname' => 'Петров',
            'name' => 'Иван',
            'email' => 'guest-search@example.com',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/users?search=Иванов')
            ->assertOk()
            ->assertSee($arbiter->email)
            ->assertDontSee($blocked->email);

        $this->actingAs($this->admin)
            ->get('/admin/users?search=guest-search')
            ->assertOk()
            ->assertSee($blocked->email)
            ->assertDontSee($arbiter->email);

        $this->actingAs($this->admin)
            ->get('/admin/users?role=arbiter')
            ->assertOk()
            ->assertSee($arbiter->email)
            ->assertDontSee($blocked->email);

        $this->actingAs($this->admin)
            ->get('/admin/users?status=blocked')
            ->assertOk()
            ->assertSee($blocked->email)
            ->assertDontSee($arbiter->email);
    }

    public function test_admin_can_create_user_and_reset_link_is_sent(): void
    {
        Notification::fake();
        $this->spyAudit();

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Новый',
            'surname' => 'Судья',
            'email' => 'new-arbiter@example.com',
            'role' => 'arbiter',
        ]);

        $response->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

        $user = User::query()->where('email', 'new-arbiter@example.com')->firstOrFail();

        $this->assertSame(UserRole::Arbiter, $user->role);
        $this->assertFalse($user->is_blocked);
        $this->assertSame(1, $user->version);
        $this->assertNotSame('', $user->password);

        Notification::assertSentTo($user, ResetPassword::class);
        Log::shouldHaveReceived('info')
            ->with('user.created', Mockery::type('array'))
            ->once();
    }

    public function test_create_user_validation_rejects_duplicate_email_and_invalid_role(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Дубль',
            'surname' => 'Почты',
            'email' => $this->admin->email,
            'role' => 'guest',
        ])->assertSessionHasErrors('email');

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Плохая',
            'surname' => 'Роль',
            'email' => 'invalid-role@example.com',
            'role' => 'superuser',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-role@example.com']);
    }

    public function test_admin_can_change_role_and_change_is_audited(): void
    {
        $target = User::factory()->guest()->create();
        $this->spyAudit();

        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'surname' => $target->surname,
            'email' => $target->email,
            'role' => 'arbiter',
            'version' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

        $target->refresh();
        $this->assertSame(UserRole::Arbiter, $target->role);
        $this->assertSame(2, $target->version);

        Log::shouldHaveReceived('info')
            ->with('user.role_changed', Mockery::on(
                fn (array $context) => ($context['context']['from'] ?? null) === 'guest'
                    && ($context['context']['to'] ?? null) === 'arbiter'
            ))
            ->once();
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->admin))
            ->put(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'surname' => $this->admin->surname,
                'email' => $this->admin->email,
                'role' => 'guest',
                'version' => 1,
            ]);

        $response->assertRedirect(route('admin.users.edit', $this->admin))->assertSessionHas('error');
        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);
    }

    public function test_admin_cannot_block_self_or_last_active_admin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.block', $this->admin), ['version' => 1])
            ->assertForbidden();

        $this->assertFalse($this->admin->fresh()->is_blocked);
        $this->assertFalse($this->admin->can('block', $this->admin));

        $blockedAdmin = User::factory()->admin()->blocked()->create();
        $this->assertFalse($blockedAdmin->can('block', $this->admin));
    }

    public function test_admin_can_block_and_unblock_user_with_audit_and_session_cleanup(): void
    {
        config(['session.driver' => 'database']);

        $target = User::factory()->guest()->create();
        $this->spyAudit();

        DB::table('sessions')->insert([
            'id' => 'user-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.block', $target), [
                'reason' => 'Нарушение правил',
                'version' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertTrue($target->is_blocked);
        $this->assertNotNull($target->blocked_at);
        $this->assertSame('Нарушение правил', $target->blocked_reason);
        $this->assertSame(2, $target->version);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);

        Log::shouldHaveReceived('info')
            ->with('user.blocked', Mockery::type('array'))
            ->once();

        $this->actingAs($this->admin)
            ->post(route('admin.users.unblock', $target), ['version' => 2])
            ->assertRedirect()
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertFalse($target->is_blocked);
        $this->assertNull($target->blocked_at);
        $this->assertNull($target->blocked_reason);
        $this->assertSame(3, $target->version);

        Log::shouldHaveReceived('info')
            ->with('user.unblocked', Mockery::type('array'))
            ->once();
    }

    public function test_block_with_stale_version_returns_409(): void
    {
        $target = User::factory()->guest()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.users.block', $target), ['version' => 5])
            ->assertStatus(409);

        $this->assertFalse($target->fresh()->is_blocked);
    }

    public function test_update_with_stale_version_returns_409(): void
    {
        $target = User::factory()->guest()->create();
        $originalName = $target->name;

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $target), [
                'name' => 'Новое',
                'surname' => 'Имя',
                'email' => $target->email,
                'role' => 'arbiter',
                'version' => 5,
            ])
            ->assertStatus(409);

        $target->refresh();
        $this->assertSame(UserRole::Guest, $target->role);
        $this->assertSame($originalName, $target->name);
    }

    public function test_blocked_user_cannot_log_in(): void
    {
        User::factory()->blocked()->create(['email' => 'blocked-login@example.com']);

        $this->post('/login', [
            'email' => 'blocked-login@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_blocked_user_is_logged_out_by_middleware(): void
    {
        $blocked = User::factory()->blocked()->create();

        $this->actingAs($blocked)
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_admin_can_request_password_reset_with_audit(): void
    {
        Notification::fake();
        $this->spyAudit();

        $target = User::factory()->arbiter()->create(['email' => 'reset-admin-panel@example.com']);

        $this->actingAs($this->admin)
            ->post(route('admin.users.reset-password', $target))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($target, ResetPassword::class);

        Log::shouldHaveReceived('info')
            ->with('user.password_reset_requested', Mockery::type('array'))
            ->once();
    }

    public function test_admin_list_does_not_offer_block_action_for_self(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee(route('admin.users.block', $this->admin), false);
    }

    public function test_admin_sees_users_link_in_navigation_and_dashboard_while_non_admins_do_not(): void
    {
        $arbiter = User::factory()->arbiter()->create();
        $guest = User::factory()->guest()->create();

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('admin.users.index'), false)
            ->assertSee(__('nav.users'));

        $this->actingAs($arbiter)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false);

        $this->actingAs($guest)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false);
    }

    private function spyAudit(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('audit')->andReturnSelf();
    }
}
