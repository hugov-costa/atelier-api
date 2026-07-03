<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glazes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('glaze_supplier_id')->constrained('glaze_suppliers')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->string('name');
            $table->unsignedInteger('price')->default(0)->comment('Price per liter in cents.');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glazes');
    }
};
