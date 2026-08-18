<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ApiStandardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_use_the_standard_problem_format(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJson([
                'type' => 'https://httpstatuses.com/422',
                'title' => 'Validation failed',
                'status' => 422,
                'message' => 'The provided data is invalid.',
            ])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authentication_authorization_and_not_found_errors_are_standardized(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertJsonPath('status', 401)
            ->assertJsonStructure(['type', 'title', 'status', 'message']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonStructure(['type', 'title', 'status', 'message']);

        $this->getJson('/api/vehicles/999999')
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonStructure(['type', 'title', 'status', 'message']);
    }

    public function test_cors_allows_only_a_configured_frontend_origin(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.com']]);

        $this->withHeaders([
            'Origin' => 'https://app.example.com',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/vehicles')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');

        $this->flushHeaders();

        $this->withHeaders([
            'Origin' => 'https://untrusted.example.com',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/vehicles')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_unexpected_exceptions_return_a_safe_standard_error(): void
    {
        Route::get('/api/test/internal-error', function (): never {
            throw new RuntimeException('Sensitive internal detail');
        });

        $this->getJson('/api/test/internal-error')
            ->assertInternalServerError()
            ->assertJson([
                'type' => 'https://httpstatuses.com/500',
                'title' => 'Internal server error',
                'status' => 500,
                'message' => 'An unexpected error occurred.',
            ])
            ->assertJsonMissing(['Sensitive internal detail']);
    }
}
