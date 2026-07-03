<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_fees', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->unsignedInteger('amount')->default(0)->comment('Charged amount in cents, snapshotted at creation.');
            $table->date('due_date');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('enrollment_id');
            $table->index('due_date');
            $table->index('paid_at');
        });

        // One active tuition per enrollment per cycle: prevents duplicate fees on the
        // same due date, which would otherwise let the two statement builders disagree.
        DB::statement(
            'CREATE UNIQUE INDEX tuition_fees_enrollment_cycle_active_unique '.
            'ON tuition_fees (enrollment_id, due_date) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_fees');
    }
};
