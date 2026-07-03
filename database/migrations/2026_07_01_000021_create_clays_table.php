<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clays', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clay_supplier_id')->constrained('clay_suppliers')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->string('name');
            $table->unsignedInteger('price')->default(0)->comment('Price per kilogram in cents.');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clays');
    }
};
