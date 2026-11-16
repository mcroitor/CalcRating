<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'role:arbiter,admin'])->get('/test/arbiter-only', function () {
            return response('Arbiter Area');
        });

        Route::middleware(['web', 'role:admin'])->get('/test/admin-only', function () {
            return response('Admin Area');
        });
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/test/arbiter-only');

        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_ajax_request_returns_401(): void
    {
        $response = $this->getJson('/test/arbiter-only');

        $response->assertStatus(401);
    }

    public function test_guest_user_cannot_access_arbiter_route(): void
    {
        $guest = User::factory()->guest()->create();

        $response = $this->actingAs($guest)->get('/test/arbiter-only');

        $response->assertStatus(403);
    }

    public function test_arbiter_user_can_access_arbiter_route(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $response = $this->actingAs($arbiter)->get('/test/arbiter-only');

        $response->assertStatus(200);
        $response->assertSee('Arbiter Area');
    }

    public function test_arbiter_user_cannot_access_admin_route(): void
    {
        $arbiter = User::factory()->arbiter()->create();

        $response = $this->actingAs($arbiter)->get('/test/admin-only');

        $response->assertStatus(403);
    }

    public function test_admin_user_can_access_both_arbiter_and_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $responseArbiter = $this->actingAs($admin)->get('/test/arbiter-only');
        $responseArbiter->assertStatus(200);

        $responseAdmin = $this->actingAs($admin)->get('/test/admin-only');
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Admin Area');
    }
}
