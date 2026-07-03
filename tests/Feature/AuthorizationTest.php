<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_policy_is_resolved_and_enforced(): void
    {
        $master = User::factory()->master()->create();
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->assertTrue(Gate::forUser($master)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($alice)->allows('viewAny', User::class));

        $this->assertTrue(Gate::forUser($alice)->allows('view', $alice));
        $this->assertFalse(Gate::forUser($alice)->allows('view', $bob));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $bob));
        $this->assertTrue(Gate::forUser($master)->allows('view', $bob));
    }

    public function test_only_masters_can_manage_other_users(): void
    {
        $master = User::factory()->master()->create();
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->assertTrue(Gate::forUser($master)->allows('update', $bob));
        $this->assertTrue(Gate::forUser($master)->allows('delete', $bob));
        $this->assertFalse(Gate::forUser($master)->allows('delete', $master));

        $this->assertFalse(Gate::forUser($admin)->allows('update', $bob));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $bob));

        $this->assertTrue(Gate::forUser($alice)->allows('update', $alice));
        $this->assertFalse(Gate::forUser($alice)->allows('update', $bob));
        $this->assertFalse(Gate::forUser($alice)->allows('delete', $bob));
    }
}
