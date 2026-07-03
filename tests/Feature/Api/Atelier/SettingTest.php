<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['base_cost', 'default_profit_margin', 'tuition_monthly_cost']]);
    }

    public function test_staff_can_update_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/v1/settings', ['base_cost' => 500, 'tuition_monthly_cost' => 20000])
            ->assertOk()
            ->assertJsonPath('data.base_cost', 500);

        $this->assertDatabaseHas('settings', ['base_cost' => 500, 'tuition_monthly_cost' => 20000]);
    }

    public function test_students_cannot_access_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->putJson('/api/v1/settings', ['base_cost' => 1])->assertForbidden();
    }

    public function test_settings_require_authentication(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
    }
}
