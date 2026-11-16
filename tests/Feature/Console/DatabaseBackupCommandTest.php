<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    public function test_sqlite_backup_and_verify_restore_roundtrip(): void
    {
        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'calcrating_backup_test_'.uniqid();
        $backupDir = $tmpDir.DIRECTORY_SEPARATOR.'backups';
        $sourceDb = $tmpDir.DIRECTORY_SEPARATOR.'source.sqlite';

        File::ensureDirectoryExists($tmpDir);
        File::ensureDirectoryExists($backupDir);
        touch($sourceDb);

        $previousDb = config('database.connections.sqlite.database');
        config(['database.connections.sqlite.database' => $sourceDb]);
        DB::purge('sqlite');

        try {
            Artisan::call('migrate', ['--force' => true]);

            User::factory()->admin()->create([
                'email' => 'backup-admin@example.com',
            ]);

            $oldBackup = $backupDir.DIRECTORY_SEPARATOR.'db-20200101-000000.sqlite';
            file_put_contents($oldBackup, 'stale');
            touch($oldBackup, now()->subDays(20)->getTimestamp());

            $this->artisan('db:backup', [
                '--path' => $backupDir,
                '--keep' => 14,
            ])
                ->expectsOutputToContain('Backup created:')
                ->expectsOutputToContain('Pruned 1 old backup(s).')
                ->assertSuccessful();

            $this->assertFileDoesNotExist($oldBackup);

            $files = File::glob($backupDir.DIRECTORY_SEPARATOR.'db-*.sqlite');
            $this->assertCount(1, $files);
            $backupFile = $files[0];

            $this->artisan('db:verify-backup', ['file' => $backupFile])
                ->expectsOutputToContain('Backup verified OK')
                ->assertSuccessful();

            $pdo = new PDO('sqlite:'.$backupFile);
            $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'backup-admin@example.com'")->fetchColumn();
            $this->assertSame(1, $count);
        } finally {
            DB::disconnect('sqlite');
            config(['database.connections.sqlite.database' => $previousDb]);
            DB::purge('sqlite');
            File::deleteDirectory($tmpDir);
        }
    }

    public function test_backup_refuses_in_memory_database(): void
    {
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->artisan('db:backup')
            ->expectsOutputToContain('in-memory')
            ->assertFailed();
    }
}
