<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_show_users(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('password');
    }

    public function test_regular_user_cannot_list_or_show_users(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/users')->assertForbidden();
        $this->getJson("/api/users/{$user->id}")->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_validated_data(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/users', [
            'name' => 'API Admin',
            'email' => 'admin@example.com',
            'password' => 'must-be-ignored',
            'is_admin' => true,
            'unknown' => 'ignored',
        ])->assertCreated()
            ->assertJsonPath('is_admin', true)
            ->assertJsonPath('first_login', true)
            ->assertJsonStructure(['temporary_password'])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('unknown');

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(Hash::check($response->json('temporary_password'), $user->password));
        $this->assertFalse(Hash::check('must-be-ignored', $user->password));
    }

    public function test_user_creation_validates_required_and_unique_fields(): void
    {
        $existing = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/users', [
            'email' => $existing->email,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_admin_can_patch_a_user_and_keep_the_same_email(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('name', 'Updated Name');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_regular_user_cannot_create_update_or_delete_users(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/users', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
        ])->assertForbidden();

        $this->patchJson("/api/users/{$target->id}", ['name' => 'Blocked'])->assertForbidden();
        $this->deleteJson("/api/users/{$target->id}")->assertForbidden();
    }

    public function test_admin_can_delete_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_routes_require_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
        $this->postJson('/api/users')->assertUnauthorized();
    }
}
