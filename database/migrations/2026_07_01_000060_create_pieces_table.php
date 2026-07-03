<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clay_id')->constrained('clays')->cascadeOnDelete();
            $table->foreignId('commission_order_id')->nullable()->constrained('commission_orders')->nullOnDelete();
            $table->foreignId('glaze_id')->nullable()->constrained('glazes')->nullOnDelete();
            $table->foreignId('piece_category_id')->nullable()->constrained('piece_categories')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('base_cost')->comment('Base cost applied in cents (0 for student pieces).');
            $table->decimal('clay_amount', 6, 3)->comment('Kilograms of clay used.');
            $table->unsignedInteger('clay_unit_price')->comment('Clay price per kg in cents, at creation.');
            $table->decimal('glaze_amount', 6, 3)->nullable()->comment('Liters of glaze used.');
            $table->unsignedInteger('glaze_unit_price')->nullable()
                ->comment('Glaze price per liter in cents, at creation.');
            $table->string('kind')
                ->comment(
                    'Who the piece is for: commission (sold, includes base cost + margin) or student (materials only).'
                );
            $table->string('name');
            $table->unsignedInteger('price')->comment('Computed selling price in cents.');
            $table->unsignedInteger('production_cost')->comment('Computed production cost in cents.');
            $table->decimal('profit_margin', 6, 2)->nullable()
                ->comment('Profit margin applied (null for student pieces).');
            $table->timestamps();
            $table->softDeletes();

            $table->index('clay_id');
            $table->index('commission_order_id');
            $table->index('glaze_id');
            $table->index('piece_category_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces');
    }
};
