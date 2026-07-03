<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('annual_fee')->default(0)
                ->comment('Annual enrollment fee owed, in cents.');
            $table->date('annual_fee_due_date')->nullable()
                ->comment('When the annual fee falls due; drives reminders.');
            $table->boolean('annual_fee_is_paid')->default(false);
            $table->timestamp('annual_fee_paid_at')->nullable()
                ->comment('When the annual fee was paid; feeds monthly revenue reports.');
            $table->boolean('is_exempt_from_annual_fee')->default(false);
            $table->boolean('is_exempt_from_piece_charges')->default(false);
            $table->boolean('is_exempt_from_tuition_fee')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('annual_fee_due_date');
            $table->index('annual_fee_paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
