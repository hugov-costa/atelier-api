<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the ceramics-atelier profile fields and allow a password-less account.
     *
     * Staff create accounts without a password; the owner sets it later through
     * the set-password flow, so the column must be nullable.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
            $table->date('admission_date')->nullable();
            $table->date('birthday')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['admission_date', 'birthday', 'phone', 'is_active']);
        });
    }
};
