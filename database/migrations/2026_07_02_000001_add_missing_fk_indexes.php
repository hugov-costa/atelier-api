<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres does not automatically create indexes on foreign key columns
     * when using constrained(). These missing indexes were identified during
     * a performance audit — they affect JOIN performance on frequently queried
     * relationships.
     */
    public function up(): void
    {
        Schema::table('clays', function (Blueprint $table): void {
            $table->index('clay_supplier_id');
        });

        Schema::table('glazes', function (Blueprint $table): void {
            $table->index('glaze_supplier_id');
        });

        Schema::table('piece_charges', function (Blueprint $table): void {
            $table->index('tuition_fee_id');
        });

        Schema::table('impersonations', function (Blueprint $table): void {
            $table->index('impersonator_id');
        });
    }

    public function down(): void
    {
        Schema::table('impersonations', function (Blueprint $table): void {
            $table->dropIndex(['impersonator_id']);
        });

        Schema::table('piece_charges', function (Blueprint $table): void {
            $table->dropIndex(['tuition_fee_id']);
        });

        Schema::table('glazes', function (Blueprint $table): void {
            $table->dropIndex(['glaze_supplier_id']);
        });

        Schema::table('clays', function (Blueprint $table): void {
            $table->dropIndex(['clay_supplier_id']);
        });
    }
};
