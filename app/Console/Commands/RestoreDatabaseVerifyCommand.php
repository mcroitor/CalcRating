<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;

class RestoreDatabaseVerifyCommand extends Command
{
    protected $signature = 'db:verify-backup {file : Path to the SQLite backup file}';

    protected $description = 'Verify SQLite backup integrity and core application tables';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! File::isFile($file)) {
            $this->error("Backup file not found: {$file}");

            return self::FAILURE;
        }

        $pdo = new PDO('sqlite:'.$file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if (strtolower(trim($integrity)) !== 'ok') {
            $this->error("SQLite integrity_check failed: {$integrity}");

            return self::FAILURE;
        }

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $required = ['users', 'clubs', 'players', 'tournaments', 'ratings'];
        $missing = array_diff($required, $tables);

        if (! empty($missing)) {
            $this->error('Backup is missing required tables: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $playersCount = (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();
        $tournamentsCount = (int) $pdo->query('SELECT COUNT(*) FROM tournaments')->fetchColumn();

        $this->info("Backup verified OK (users: {$usersCount}, players: {$playersCount}, tournaments: {$tournamentsCount}).");

        return self::SUCCESS;
    }
}
