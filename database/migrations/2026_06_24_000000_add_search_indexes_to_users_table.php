<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Trigram GIN indexes let the user search (`name`/`email` with a leading-wildcard
     * `LIKE`) use an index instead of scanning the whole table. PostgreSQL only.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS users_name_trgm_idx ON users USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_email_trgm_idx ON users USING gin (email gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS users_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS users_email_trgm_idx');
    }
};
