<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $connection = is_string($connection) ? $connection : null;

        $table = config('audit.drivers.database.table', 'audits');
        $table = is_string($table) ? $table : 'audits';

        $morphPrefix = config('audit.user.morph_prefix', 'user');
        $morphPrefix = is_string($morphPrefix) ? $morphPrefix : 'user';

        Schema::connection($connection)->create($table, function (Blueprint $table) use ($morphPrefix) {
            $table->bigIncrements('id');
            $table->string($morphPrefix.'_type')->nullable();
            $table->unsignedBigInteger($morphPrefix.'_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->string('impersonator_id')->nullable();
            $table->timestamps();

            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $connection = is_string($connection) ? $connection : null;

        $table = config('audit.drivers.database.table', 'audits');
        $table = is_string($table) ? $table : 'audits';

        Schema::connection($connection)->drop($table);
    }
};
