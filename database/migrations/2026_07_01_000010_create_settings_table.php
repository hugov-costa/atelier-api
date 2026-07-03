<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton table holding the atelier's pricing and billing configuration.
     * A single row is seeded so the application always has settings to read.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('annual_enrollment_cost')->default(0);
            $table->unsignedInteger('base_cost')->default(0)
                ->comment('Baseline production cost (in cents) added to every piece.');
            $table->decimal('clay_amount_multiplier', 6, 2)->default(1)
                ->comment('Per full kilogram of clay, multiplies a piece price before profit margin.');
            $table->decimal('default_profit_margin', 6, 2)->default(1)
                ->comment('Multiplies a piece price after all costs, unless the category overrides it.');
            $table->string('logo_path')->nullable()
                ->comment('Path to the atelier logo on public object storage; embedded in billing statements.');
            $table->unsignedSmallInteger('piece_charge_billing_grace_days')->default(20)
                ->comment(
                    'Days after a cycle due date within which a new student piece is billed on the next tuition;'
                    .' later pieces are pushed one cycle further.'
                );
            $table->unsignedTinyInteger('tuition_fee_due_day_of_month')->default(1);
            $table->unsignedInteger('tuition_monthly_cost')->default(0);
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'annual_enrollment_cost'          => 0,
            'base_cost'                       => 0,
            'clay_amount_multiplier'          => 1,
            'default_profit_margin'           => 1,
            'tuition_fee_due_day_of_month'    => 1,
            'tuition_monthly_cost'            => 0,
            'piece_charge_billing_grace_days' => 20,
            'logo_path'                       => null,
            'created_at'                      => now(),
            'updated_at'                      => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
