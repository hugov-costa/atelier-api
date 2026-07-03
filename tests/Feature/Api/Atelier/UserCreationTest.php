<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_create_a_passwordless_user_and_send_a_set_password_link(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/users', [
            'name'  => 'New Student',
            'email' => 'student@example.com',
            'phone' => '11987654321',
        ])->assertCreated()->assertJsonPath('data.role', 'user')->assertJsonPath('data.has_password', false);

        $user = User::where('email', 'student@example.com')->firstOrFail();
        $this->assertNull($user->password);

        Notification::assertSentTo($user, SetPasswordNotification::class);
    }

    public function test_admins_cannot_assign_elevated_roles(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/users', [
            'name'  => 'Wannabe Admin',
            'email' => 'wannabe@example.com',
            'role'  => 'admin',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_masters_create_admins_with_the_same_passwordless_flow(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->master()->create());

        $this->postJson('/api/v1/users', [
            'name'  => 'New Admin',
            'email' => 'newadmin@example.com',
            'role'  => 'admin',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.has_password', false);

        $admin = User::where('email', 'newadmin@example.com')->firstOrFail();
        $this->assertNull($admin->password);
        $this->assertTrue($admin->isAdmin());

        Notification::assertSentTo($admin, SetPasswordNotification::class);
    }

    public function test_masters_can_create_other_masters(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->master()->create());

        $this->postJson('/api/v1/users', [
            'name'  => 'Co Owner',
            'email' => 'coowner@example.com',
            'role'  => 'master',
        ])->assertCreated()->assertJsonPath('data.role', 'master');
    }

    public function test_students_cannot_create_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
    }

    public function test_master_bootstrap_only_works_on_an_empty_installation(): void
    {
        $this->postJson('/api/v1/users/master', [
            'name'                  => 'Owner',
            'email'                 => 'owner@example.com',
            'password'              => 'Sup3r!secret',
            'password_confirmation' => 'Sup3r!secret',
        ])->assertCreated()->assertJsonPath('data.role', 'master');

        $this->postJson('/api/v1/users/master', [
            'name'                  => 'Second Owner',
            'email'                 => 'owner2@example.com',
            'password'              => 'Sup3r!secret',
            'password_confirmation' => 'Sup3r!secret',
        ])->assertStatus(409);
    }
}
