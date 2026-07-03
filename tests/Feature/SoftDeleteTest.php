<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_soft_deleted_and_audited(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audits', [
            'auditable_id'   => $user->id,
            'auditable_type' => User::class,
            'event'          => 'deleted',
        ]);

        $user->restore();

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audits', [
            'auditable_id' => $user->id,
            'event'        => 'restored',
        ]);
    }

    public function test_soft_deleted_users_are_excluded_from_queries(): void
    {
        $active = User::factory()->create();
        $deleted = User::factory()->create();

        $deleted->delete();

        $ids = User::query()->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($deleted->id));
    }
}
