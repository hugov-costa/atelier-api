<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A receivable owed by a student for a piece they made (materials + firing). It is
 * billed on a tuition cycle and settled together with that tuition; the amount is
 * snapshotted from the piece's price so it never drifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_charges', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('piece_id')->unique()->constrained('pieces')->cascadeOnDelete();
            $table->foreignId('tuition_fee_id')->nullable()->constrained('tuition_fees')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount')->comment('Amount owed in cents, snapshotted from the piece price.');
            $table->date('due_date')->comment('The tuition cycle due date this charge is billed on.');
            $table->timestamp('paid_at')->nullable()->comment('Set when the billing tuition is paid.');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_charges');
    }
};
