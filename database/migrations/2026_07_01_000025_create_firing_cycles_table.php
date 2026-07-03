<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firing_cycles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->unsignedTinyInteger('cycle')->comment('Firing pass number (e.g. 1 = bisque, 2 = glaze).');
            $table->unsignedInteger('duration')->comment('Duration in minutes.');
            $table->string('name');
            $table->unsignedInteger('price_per_unit')->comment('Cost charged per piece in cents.');
            $table->decimal('temperature', 8, 2)->comment('Peak temperature in degrees Celsius.');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firing_cycles');
    }
};
