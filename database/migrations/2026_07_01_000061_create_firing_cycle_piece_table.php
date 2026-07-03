<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firing_cycle_piece', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firing_cycle_id')->constrained('firing_cycles')->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained('pieces')->cascadeOnDelete();
            $table->unsignedInteger('price')->comment('Firing cost captured for this piece in cents.');
            $table->timestamps();

            $table->unique(['firing_cycle_id', 'piece_id']);
            $table->index('piece_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firing_cycle_piece');
    }
};
