<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
        {--path= : Target directory for backups (defaults to BACKUP_PATH or storage/backups)}
        {--keep= : Retention period in days (defaults to BACKUP_RETENTION_DAYS or 14)}';

    protected $description = 'Create a consistent SQLite database backup using VACUUM INTO and prune old backups';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->error('db:backup supports only sqlite connections.');

            return self::FAILURE;
        }

        $sourcePath = (string) config('database.connections.sqlite.database');
        if ($sourcePath === ':memory:' || $sourcePath === '') {
            $this->error('Cannot back up an in-memory SQLite database.');

            return self::FAILURE;
        }

        $targetDir = (string) ($this->option('path')
            ?: env('BACKUP_PATH')
            ?: storage_path('backups'));

        File::ensureDirectoryExists($targetDir, 0750);

        $timestamp = now()->format('Ymd-His');
        $targetFile = rtrim($targetDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."db-{$timestamp}.sqlite";

        if (File::exists($targetFile)) {
            $targetFile = rtrim($targetDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."db-{$timestamp}-".uniqid().'.sqlite';
        }

        $escaped = str_replace("'", "''", $targetFile);
        DB::connection('sqlite')->getPdo()->exec("VACUUM INTO '{$escaped}'");

        $keepDays = (int) ($this->option('keep') !== null
            ? $this->option('keep')
            : env('BACKUP_RETENTION_DAYS', 14));

        $deleted = 0;
        if ($keepDays > 0) {
            $cutoff = now()->subDays($keepDays)->getTimestamp();
            foreach (File::glob(rtrim($targetDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'db-*.sqlite') as $file) {
                if ($file !== $targetFile && File::lastModified($file) < $cutoff) {
                    File::delete($file);
                    $deleted++;
                }
            }
        }

        Log::info('SQLite backup created', [
            'path' => $targetFile,
            'size_bytes' => File::size($targetFile),
            'pruned_count' => $deleted,
        ]);

        $this->info("Backup created: {$targetFile}");
        if ($deleted > 0) {
            $this->info("Pruned {$deleted} old backup(s).");
        }

        return self::SUCCESS;
    }
}
