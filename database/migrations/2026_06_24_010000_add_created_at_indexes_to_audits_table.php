<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail is ordered by `created_at` (listing) and pruned by `created_at`.
     * Without these indexes the growing audits table forces a full sort/scan per request.
     */
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            $table->index('created_at', 'audits_created_at_idx');
            $table->index(
                ['auditable_type', 'auditable_id', 'created_at'],
                'audits_auditable_created_at_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            $table->dropIndex('audits_created_at_idx');
            $table->dropIndex('audits_auditable_created_at_idx');
        });
    }
};
