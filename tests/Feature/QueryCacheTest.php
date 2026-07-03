<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_lists_are_cached_until_an_eloquent_write_invalidates_them(): void
    {
        User::factory()->count(3)->create();

        $service = app(UserService::class);

        $this->assertSame(3, $service->paginate()->total());

        DB::table('users')->insert([
            'ulid'     => (string) Str::ulid(),
            'name'     => 'Raw Insert',
            'email'    => 'raw@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->assertSame(3, $service->paginate()->total());

        User::factory()->create();

        $this->assertSame(5, $service->paginate()->total());
    }

    public function test_cache_can_be_disabled(): void
    {
        config(['cache.query.enabled' => false]);

        $service = app(UserService::class);

        User::factory()->count(2)->create();
        $this->assertSame(2, $service->paginate()->total());

        DB::table('users')->insert([
            'ulid'     => (string) Str::ulid(),
            'name'     => 'Raw Insert',
            'email'    => 'raw@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->assertSame(3, $service->paginate()->total());
    }
}
