<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlitePragmasTest extends TestCase
{
    public function test_sqlite_configuration_matches_specification(): void
    {
        $config = config('database.connections.sqlite');

        $this->assertEquals('sqlite', $config['driver']);
        $this->assertTrue($config['foreign_key_constraints']);
        $this->assertEquals(5000, $config['busy_timeout']);
        $this->assertEquals('WAL', $config['journal_mode']);
        $this->assertEquals('NORMAL', $config['synchronous']);
    }

    public function test_sqlite_active_pragmas(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test only runs on SQLite');
        }

        $foreignKeys = DB::select('PRAGMA foreign_keys');
        $this->assertEquals(1, (int) ($foreignKeys[0]->foreign_keys ?? 0));

        $busyTimeout = DB::select('PRAGMA busy_timeout');
        $this->assertEquals(5000, (int) ($busyTimeout[0]->timeout ?? 0));

        $synchronous = DB::select('PRAGMA synchronous');
        $this->assertEquals(1, (int) ($synchronous[0]->synchronous ?? 0));
    }
}
