<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_when_dependencies_are_reachable(): void
    {
        Storage::fake('minio_public');

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.checks.cache', 'ok')
            ->assertJsonPath('data.checks.storage', 'ok');
    }

    public function test_health_endpoint_returns_503_when_a_dependency_is_degraded(): void
    {
        $this->instance(HealthService::class, new class extends HealthService
        {
            public function check(): array
            {
                return ['status' => 'degraded', 'checks' => ['database' => 'down']];
            }
        });

        $this->getJson('/api/v1/health')
            ->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE)
            ->assertJsonPath('data.status', 'degraded');
    }
}
