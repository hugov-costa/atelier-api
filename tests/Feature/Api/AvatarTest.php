<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_upload_and_remove_their_avatar(): void
    {
        Storage::fake('minio_public');
        Storage::fake('minio_private');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post("/api/v1/users/{$user->ulid}/avatar", [
            'avatar' => UploadedFile::fake()->image('avatar.png', 1000, 1000),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->ulid)
            ->assertJsonStructure(['data' => ['avatar_url']]);

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('minio_public')->assertExists($user->avatar_path);

        $this->deleteJson("/api/v1/users/{$user->ulid}/avatar")->assertNoContent();

        $user->refresh();
        $this->assertNull($user->avatar_path);
    }

    public function test_avatar_must_be_an_image_within_the_size_limit(): void
    {
        Storage::fake('minio_public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post("/api/v1/users/{$user->ulid}/avatar", [
            'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post("/api/v1/users/{$user->ulid}/avatar", [
            'avatar' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_a_user_cannot_set_another_users_avatar(): void
    {
        Storage::fake('minio_public');
        $actor = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($actor);

        $this->post("/api/v1/users/{$other->ulid}/avatar", [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }
}
