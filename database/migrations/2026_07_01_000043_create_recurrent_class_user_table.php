<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrent_class_user', function (Blueprint $table): void {
            $table->foreignId('recurrent_class_id')->constrained('recurrent_classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['recurrent_class_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrent_class_user');
    }
};
