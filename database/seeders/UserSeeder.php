<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the users table.
     */
    public function run(): void
    {
        User::factory()->master()->create([
            'name'  => 'Master User',
            'email' => 'master@example.com',
        ]);

        User::factory()->admin()->create([
            'name'  => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->count(10)->create();
    }
}
