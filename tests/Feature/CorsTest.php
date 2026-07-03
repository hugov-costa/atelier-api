<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_the_csrf_cookie_endpoint_is_cors_enabled_for_the_spa(): void
    {
        $this->get('/sanctum/csrf-cookie', ['Origin' => 'http://localhost:3000'])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_api_routes_are_cors_enabled_for_the_spa(): void
    {
        $this->getJson('/api/v1/health', ['Origin' => 'http://localhost:3000'])
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Expose-Headers', 'X-Request-Id');
    }
}
