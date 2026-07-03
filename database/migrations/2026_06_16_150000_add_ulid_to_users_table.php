<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });

        foreach (DB::table('users')->whereNull('ulid')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['ulid' => (string) Str::ulid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }
};
