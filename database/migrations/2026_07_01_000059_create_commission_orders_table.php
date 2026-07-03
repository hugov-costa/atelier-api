<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('delivery_date')->nullable();
            $table->string('description')->nullable();
            $table->date('order_date');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedInteger('sale_total_override')->nullable()
                ->comment('Negotiated total in cents that overrides the derived sum of piece prices.');
            $table->unsignedInteger('shipping_charged')->default(0)
                ->comment('Shipping charged to the customer in cents; added to the sale total.');
            $table->unsignedInteger('shipping_cost')->default(0)
                ->comment('Shipping paid by the atelier in cents; reduces the realized margin.');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('order_date');
            $table->index('paid_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_orders');
    }
};
