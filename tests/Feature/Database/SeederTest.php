<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_expected_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame(1, $admin->version);

        $arbiter = User::where('email', 'arbiter@example.com')->first();
        $this->assertNotNull($arbiter);
        $this->assertSame(UserRole::Arbiter, $arbiter->role);
        $this->assertTrue(Hash::check('password', $arbiter->password));
        $this->assertSame(1, $arbiter->version);

        $guest = User::where('email', 'guest@example.com')->first();
        $this->assertNotNull($guest);
        $this->assertSame(UserRole::Guest, $guest->role);
        $this->assertTrue(Hash::check('password', $guest->password));
        $this->assertSame(1, $guest->version);

        $this->assertDatabaseHas('players', [
            'last_name' => 'Каспаров',
            'current_rating' => 2800,
        ]);
        $this->assertDatabaseHas('players', [
            'last_name' => 'Карлсен',
            'current_rating' => 2850,
        ]);
    }

    public function test_database_seeder_refuses_to_run_in_production(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('admin:bootstrap');

            $this->artisan('db:seed', ['--force' => true]);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
