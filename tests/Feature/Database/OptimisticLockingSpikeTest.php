<?php

namespace Tests\Feature\Database;

use App\Exceptions\ConcurrentModificationException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OptimisticLockingSpikeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/test/users/{user}/optimistic-update', function (Request $request, User $user) {
            $expectedVersion = (int) $request->input('version');

            $user->updateWithVersion([
                'name' => $request->input('name'),
            ], $expectedVersion);

            return response()->json([
                'success' => true,
                'version' => $user->version,
                'name' => $user->name,
            ]);
        });
    }

    public function test_first_update_succeeds_and_increments_version(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'version' => 1,
        ]);

        $user->updateWithVersion(['name' => 'Updated Name'], 1);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame(2, $user->version);
    }

    public function test_concurrent_update_with_stale_version_throws_exception(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'version' => 1,
        ]);

        // First user updates version 1 -> version 2
        $userCopy1 = User::find($user->id);
        $userCopy2 = User::find($user->id);

        $userCopy1->updateWithVersion(['name' => 'First Update'], 1);

        $this->expectException(ConcurrentModificationException::class);

        // Second user attempts to update with stale version 1
        $userCopy2->updateWithVersion(['name' => 'Second Update'], 1);
    }

    public function test_concurrent_update_over_http_returns_409_conflict(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'version' => 1,
        ]);

        // First request succeeds
        $response1 = $this->postJson("/test/users/{$user->id}/optimistic-update", [
            'version' => 1,
            'name' => 'Winner Update',
        ]);

        $response1->assertStatus(200);
        $response1->assertJson([
            'success' => true,
            'version' => 2,
            'name' => 'Winner Update',
        ]);

        // Second concurrent request with stale version 1 returns 409 Conflict
        $response2 = $this->postJson("/test/users/{$user->id}/optimistic-update", [
            'version' => 1,
            'name' => 'Loser Update',
        ]);

        $response2->assertStatus(409);
        $response2->assertJson([
            'message' => __('errors.409.message'),
            'code' => 'CONCURRENT_MODIFICATION',
        ]);

        // Verify database state: winning update is preserved
        $user->refresh();
        $this->assertSame('Winner Update', $user->name);
        $this->assertSame(2, $user->version);
    }

    public function test_html_request_returns_409_view(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'version' => 1,
        ]);

        // Advance version to 2
        $user->updateWithVersion(['name' => 'First Update'], 1);

        // Attempt stale update with HTML request
        $response = $this->post("/test/users/{$user->id}/optimistic-update", [
            'version' => 1,
            'name' => 'Stale Update',
        ]);

        $response->assertStatus(409);
        $response->assertSee(__('errors.409.heading'));
        $response->assertSee(__('errors.409.message'));
    }
}
