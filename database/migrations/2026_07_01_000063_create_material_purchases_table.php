<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_purchases', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('freight')->default(0)
                ->comment(
                    'Freight/shipping portion in cents, included in total_price; not folded into the unit price.'
                );
            $table->string('invoice_number')->nullable();
            $table->string('lot')->nullable();
            $table->morphs('material');
            $table->string('payment_method');
            $table->date('purchase_date');
            $table->decimal('quantity', 10, 3)->comment('Kilograms of clay or number of jars.');
            $table->date('receipt_date')->nullable()->comment('Set when the goods physically arrive.');
            $table->morphs('supplier');
            $table->unsignedInteger('total_price')
                ->comment('Authoritative total in cents (goods + freight, may include discount).');
            $table->unsignedInteger('unit_price')->comment('Unit price in cents (per kg of clay or per jar of glaze).');
            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_purchases');
    }
};
