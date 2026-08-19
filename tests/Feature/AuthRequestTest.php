<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
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
            ->assertJsonPath('type', 'https://httpstatuses.com/401')
            ->assertJsonPath('title', 'Invalid credentials')
            ->assertJsonPath('status', 401)
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_first_login_user_must_provide_the_correct_temporary_password(): void
    {
        $user = User::factory()->create([
            'email' => 'first-login@example.com',
            'password' => 'temporary-password',
            'first_login' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'temporary-password',
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

    public function test_finishing_first_login_revokes_other_temporary_password_tokens(): void
    {
        $user = User::factory()->create(['first_login' => true]);
        $currentToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($currentToken)->postJson('/api/auth/password', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull(PersonalAccessToken::findToken($currentToken));
        $this->assertNull(PersonalAccessToken::findToken($otherToken));
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

    public function test_user_can_register_publicly_with_a_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'is_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('name', 'New User')
            ->assertJsonPath('is_admin', false)
            ->assertJsonPath('first_login', false)
            ->assertJsonMissingPath('temporary_password')
            ->assertJsonMissingPath('password');

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->first_login);
        $this->assertTrue(Hash::check('safe-password', $user->password));
    }

    public function test_public_registration_requires_valid_unique_data_and_confirmation(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => $user->email,
            'password' => 'safe-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_public_registration_is_rate_limited(): void
    {
        foreach (range(1, 5) as $index) {
            $this->postJson('/api/auth/register', [
                'name' => "User {$index}",
                'email' => "user{$index}@example.com",
                'password' => 'safe-password',
                'password_confirmation' => 'safe-password',
            ])->assertCreated();
        }

        $this->postJson('/api/auth/register', [
            'name' => 'Limited User',
            'email' => 'limited@example.com',
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
        ])->assertTooManyRequests()
            ->assertJsonPath('status', 429)
            ->assertJsonStructure(['type', 'title', 'status', 'message']);
    }

    public function test_user_cannot_replace_a_password_after_finishing_first_login(): void
    {
        $user = User::factory()->create(['first_login' => false]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/password', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertForbidden();
    }

    public function test_first_login_token_can_only_access_first_login_auth_routes(): void
    {
        $user = User::factory()->create([
            'password' => 'temporary-password',
            'first_login' => true,
        ]);
        $token = $user->createToken('first-login')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('first_login', true);

        $this->getJson('/api/vehicles')
            ->assertForbidden()
            ->assertJsonStructure(['type', 'title', 'status', 'message']);
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/vehicles/1/images')->assertForbidden();

        $this->postJson('/api/auth/password', [
            'password' => 'definitive-password',
            'password_confirmation' => 'definitive-password',
        ])->assertOk()
            ->assertJsonPath('first_login', false);

        $this->getJson('/api/vehicles')->assertOk();
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
        $this->postJson('/api/auth/password')->assertUnauthorized();
    }
}
