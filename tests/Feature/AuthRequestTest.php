<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_bearer_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
            'first_login' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('first_login', false)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials_and_payload(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'first_login' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'incorrect-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingValidationErrors(['password']);
    }

    public function test_first_login_user_can_login_without_a_password(): void
    {
        $user = User::factory()->create([
            'email' => 'first-login@example.com',
            'first_login' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('first_login', true)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_authenticated_user_can_create_a_password_and_finish_first_login(): void
    {
        $user = User::factory()->create(['first_login' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/password', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('first_login', false)
            ->assertJsonMissingPath('password');

        $user->refresh();

        $this->assertFalse($user->first_login);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_password_creation_requires_authentication_and_confirmation(): void
    {
        $this->postJson('/api/auth/password', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/password', [
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_admin_can_register_a_regular_user(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'must-be-ignored',
            'is_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('name', 'New User')
            ->assertJsonPath('is_admin', false)
            ->assertJsonPath('first_login', true)
            ->assertJsonStructure(['temporary_password'])
            ->assertJsonMissingPath('password');

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check($response->json('temporary_password'), $user->password));
        $this->assertFalse(Hash::check('must-be-ignored', $user->password));
    }

    public function test_register_requires_an_admin_and_valid_unique_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => $user->email,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_me_and_user_return_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('id', $user->id);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('test-token')->plainTextToken;

        $this->withToken($plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_auth_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
        $this->postJson('/api/auth/register')->assertUnauthorized();
        $this->postJson('/api/auth/password')->assertUnauthorized();
    }
}
