<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glaze_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('email');
            $table->string('name');
            $table->string('phone');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX glaze_suppliers_email_active_unique ON glaze_suppliers (email) '
            .'WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX glaze_suppliers_name_active_unique ON glaze_suppliers (name) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('glaze_suppliers');
    }
};
