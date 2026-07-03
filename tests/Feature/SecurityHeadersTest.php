<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present_on_api_responses(): void
    {
        $this->getJson('/api/v1/health')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }
}
