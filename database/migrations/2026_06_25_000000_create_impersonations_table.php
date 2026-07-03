<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('impersonator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('impersonated_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('reason');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['impersonated_id', 'created_at']);
            $table->index('token_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impersonations');
    }
};
