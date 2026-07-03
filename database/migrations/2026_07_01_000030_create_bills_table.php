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
        Schema::create('bills', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->boolean('is_recurrent')->default(false);
            $table->string('name');
            $table->unsignedTinyInteger('reference_month');
            $table->unsignedSmallInteger('reference_year');
            $table->unsignedInteger('value')->comment('Amount in cents.');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reference_year', 'reference_month']);
            $table->index('due_date');
        });

        // One active bill per name and reference month, so the recurrent-bill job
        // cannot silently create a duplicate expense for a month.
        DB::statement(
            'CREATE UNIQUE INDEX bills_name_reference_active_unique '.
            'ON bills (name, reference_year, reference_month) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
