<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_id_is_generated_and_returned_in_the_response(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/user')->assertOk();

        $requestId = $response->headers->get(AssignRequestId::HEADER);

        $this->assertNotNull($requestId);
        $this->assertNotSame('', $requestId);
    }

    public function test_an_incoming_request_id_is_echoed_back(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/user', [AssignRequestId::HEADER => 'trace-12345'])
            ->assertOk()
            ->assertHeader(AssignRequestId::HEADER, 'trace-12345');
    }

    public function test_pulse_dashboard_is_restricted_to_masters(): void
    {
        $master = User::factory()->master()->create();
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->assertTrue(Gate::forUser($master)->allows('viewPulse'));
        $this->assertFalse(Gate::forUser($admin)->allows('viewPulse'));
        $this->assertFalse(Gate::forUser($member)->allows('viewPulse'));
    }
}
