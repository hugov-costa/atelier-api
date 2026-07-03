<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('single_classes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->dateTime('end_datetime');
            $table->boolean('is_replacement')->default(false)
                ->comment('Whether the class replaces a previously missed one.');
            $table->unsignedInteger('price')->default(0)->comment('Price in cents.');
            $table->dateTime('start_datetime');
            $table->timestamps();
            $table->softDeletes();

            $table->index('start_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('single_classes');
    }
};
