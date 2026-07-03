<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrent_classes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->unsignedTinyInteger('day_of_the_week')->comment('Day of the week (1 = Sunday … 7 = Saturday).');
            $table->time('end_time');
            $table->time('start_time');
            $table->timestamps();
            $table->softDeletes();

            $table->index('day_of_the_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrent_classes');
    }
};
