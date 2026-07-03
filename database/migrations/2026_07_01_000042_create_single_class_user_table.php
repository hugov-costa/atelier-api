<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('single_class_user', function (Blueprint $table): void {
            $table->foreignId('single_class_id')->constrained('single_classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['single_class_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('single_class_user');
    }
};
