<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_export_their_personal_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/export')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="account-data.json"')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['user', 'audits', 'exported_at'], 'message']);
    }

    public function test_a_user_can_delete_their_own_account_with_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-123']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/user', ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->deleteJson('/api/v1/user', ['password' => 'secret-password-123'])
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_account_deletion_erases_personal_data(): void
    {
        Storage::fake('minio_public');

        $user = User::factory()->create([
            'name'        => 'Jane Citizen',
            'email'       => 'jane@example.com',
            'password'    => 'secret-password-123',
            'avatar_path' => 'avatars/example.webp',
        ]);
        Storage::disk('minio_public')->put('avatars/example.webp', 'binary');
        $originalHash = $user->password;
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/user', ['password' => 'secret-password-123'])
            ->assertNoContent();

        $erased = User::withTrashed()->findOrFail($user->id);

        $this->assertNotSame('Jane Citizen', $erased->name);
        $this->assertNotSame('jane@example.com', $erased->email);
        $this->assertStringEndsWith('@deleted.invalid', $erased->email);
        $this->assertNull($erased->avatar_path);
        $this->assertNotSame($originalHash, $erased->password);
        $this->assertNotNull($erased->deleted_at);
        $this->assertCount(0, $erased->tokens()->get());
        Storage::disk('minio_public')->assertMissing('avatars/example.webp');
    }

    public function test_account_deletion_redacts_personal_data_from_the_audit_trail(): void
    {
        Storage::fake('minio_public');

        $user = User::factory()->create([
            'name'     => 'Jane Citizen',
            'email'    => 'jane@example.com',
            'password' => 'secret-password-123',
        ]);
        $user->update(['name' => 'Jane Renamed']);

        $this->assertTrue(
            $user->audits()->get()->contains(
                fn ($audit) => str_contains((string) json_encode($audit->getAttribute('new_values')), 'Jane')
                    || str_contains((string) json_encode($audit->getAttribute('old_values')), 'jane@example.com')
            )
        );

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/user', ['password' => 'secret-password-123'])
            ->assertNoContent();

        foreach ($user->audits()->get() as $audit) {
            $encoded = (string) json_encode($audit->getAttribute('old_values'))
                .(string) json_encode($audit->getAttribute('new_values'));
            $this->assertStringNotContainsString('Jane Citizen', $encoded);
            $this->assertStringNotContainsString('Jane Renamed', $encoded);
            $this->assertStringNotContainsString('jane@example.com', $encoded);
        }
    }

    public function test_account_deletion_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/user', ['password' => 'whatever'])
            ->assertUnauthorized();
    }
}
