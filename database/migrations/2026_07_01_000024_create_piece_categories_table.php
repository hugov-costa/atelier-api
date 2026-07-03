<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->date('available_until')->nullable()
                ->comment('Optional date after which the category is no longer offered.');
            $table->string('name');
            $table->decimal('profit_margin', 6, 2)
                ->comment('Replaces the default profit margin for pieces in this category.');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_categories');
    }
};
