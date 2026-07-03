<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backing store for the dedicated "set password" broker. It mirrors the
     * password-reset table shape so the framework's token repository hashes the
     * token at rest and enforces expiry; only the broker (separate table) and
     * the email link differ from the reset flow.
     */
    public function up(): void
    {
        Schema::create('set_password_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_password_tokens');
    }
};
