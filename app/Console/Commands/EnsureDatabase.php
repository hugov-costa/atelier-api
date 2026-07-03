<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class EnsureDatabase extends Command
{
    protected $signature = 'app:ensure-database {--timeout=60 : Seconds to wait for the database server}';

    protected $description = 'Wait for the PostgreSQL server and create the application database if it does not exist';

    public function handle(): int
    {
        $database = config('database.connections.pgsql.database');

        if (! is_string($database) || $database === '') {
            $this->error('No PostgreSQL database name is configured.');

            return self::FAILURE;
        }

        if (! $this->waitForServer((int) $this->option('timeout'))) {
            $this->error('Database server did not become available in time.');

            return self::FAILURE;
        }

        if ($this->databaseExists($database)) {
            $this->info("Database [{$database}] already exists, skipping creation.");

            return self::SUCCESS;
        }

        DB::connection('pgsql_admin')->statement(
            sprintf('CREATE DATABASE "%s"', str_replace('"', '', $database))
        );

        $this->info("Database [{$database}] created.");

        return self::SUCCESS;
    }

    private function waitForServer(int $timeout): bool
    {
        $deadline = time() + $timeout;

        do {
            try {
                DB::connection('pgsql_admin')->getPdo();

                return true;
            } catch (Throwable) {
                $this->line('Waiting for database server...');
                sleep(2);
            }
        } while (time() < $deadline);

        return false;
    }

    private function databaseExists(string $database): bool
    {
        return DB::connection('pgsql_admin')->table('pg_database')
            ->where('datname', $database)
            ->exists();
    }
}
