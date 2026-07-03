<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_routes_return_problem_details(): void
    {
        $this->getJson('/api/v1/this-route-does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['type', 'title', 'status', 'detail'])
            ->assertJson([
                'status' => 404,
                'title'  => 'Not Found',
                'type'   => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/404',
            ]);
    }

    public function test_method_not_allowed_returns_problem_details(): void
    {
        $this->postJson('/api/v1/user')
            ->assertStatus(405)
            ->assertJson(['status' => 405, 'title' => 'Method Not Allowed']);
    }

    public function test_unauthenticated_requests_return_problem_details(): void
    {
        $this->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJson(['status' => 401, 'title' => 'Unauthorized']);
    }

    public function test_forbidden_requests_return_problem_details(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users/audits')
            ->assertForbidden()
            ->assertJson(['status' => 403, 'title' => 'Forbidden']);
    }

    public function test_validation_errors_return_problem_details(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/users/audits?event=not-a-valid-event')
            ->assertStatus(422)
            ->assertJson(['status' => 422])
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors' => ['event']]);
    }

    public function test_server_errors_are_masked_as_problem_details(): void
    {
        Route::get('/api/v1/_test/boom', fn () => throw new RuntimeException('sensitive internal detail'));

        $this->getJson('/api/v1/_test/boom')
            ->assertStatus(500)
            ->assertJson(['status' => 500, 'title' => 'Internal Server Error'])
            ->assertJsonMissing(['detail' => 'sensitive internal detail'])
            ->assertJsonMissingPath('debug');
    }

    public function test_database_errors_are_masked_as_problem_details(): void
    {
        Route::get('/api/v1/_test/db', fn () => DB::table('nonexistent_table')->get());

        $this->getJson('/api/v1/_test/db')
            ->assertStatus(500)
            ->assertJson(['status' => 500, 'title' => 'Internal Server Error']);
    }
}
