<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_searches_sorts_and_paginates_vehicles(): void
    {
        $user = User::factory()->create();
        $this->createVehicle($user, ['placa' => 'ABC1D23', 'marca' => 'Toyota', 'modelo' => 'Corolla', 'km' => 20000]);
        $expected = $this->createVehicle($user, ['placa' => 'DEF4G56', 'chassi' => '76543210987654321', 'marca' => 'Honda', 'modelo' => 'Civic', 'km' => 10000]);
        Sanctum::actingAs($user);

        $this->getJson('/api/vehicles?q=Civ&marca=Honda&sort=-km&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expected->id)
            ->assertJsonPath('per_page', 1);
    }

    public function test_index_rejects_invalid_query_parameters(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/vehicles?sort=password&per_page=101&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'per_page', 'page']);
    }

    public function test_owner_can_show_and_patch_a_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        Sanctum::actingAs($user);

        $this->getJson("/api/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('id', $vehicle->id)
            ->assertJsonStructure(['vehicle_images']);

        $this->patchJson("/api/vehicles/{$vehicle->id}", [
            'km' => 25000,
            'valor_venda' => '115000.00',
        ])->assertOk()
            ->assertJsonPath('km', 25000);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'km' => 25000,
            'valor_venda' => 115000.00,
        ]);
    }

    public function test_put_requires_all_vehicle_fields(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/vehicles/{$vehicle->id}", ['marca' => 'Honda'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'placa',
                'chassi',
                'modelo',
                'versao',
                'valor_venda',
                'cor',
                'km',
                'cambio',
                'combustivel',
            ]);
    }

    public function test_update_rejects_duplicate_plate_and_chassis(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $other = $this->createVehicle($user, [
            'placa' => 'DEF4G56',
            'chassi' => '76543210987654321',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/vehicles/{$vehicle->id}", [
            'placa' => $other->placa,
            'chassi' => $other->chassi,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['placa', 'chassi']);
    }

    public function test_owner_can_delete_a_vehicle_and_its_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $path = "vehicles/{$vehicle->id}/image.jpg";
        Storage::disk('public')->put($path, 'image');
        $image = $vehicle->vehicleImages()->create([
            'path' => $path,
            'is_cover' => true,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertNoContent();

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
        $this->assertDatabaseMissing('vehicle_image', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_regular_user_cannot_update_or_delete_another_users_vehicle(): void
    {
        $vehicle = $this->createVehicle(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/vehicles/{$vehicle->id}", ['km' => 1])->assertForbidden();
        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertForbidden();
    }

    public function test_admin_can_update_and_delete_another_users_vehicle(): void
    {
        $vehicle = $this->createVehicle(User::factory()->create());
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/vehicles/{$vehicle->id}", ['km' => 1])
            ->assertOk()
            ->assertJsonPath('km', 1);

        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertNoContent();
    }

    public function test_vehicle_routes_require_authentication(): void
    {
        $this->getJson('/api/vehicles')->assertUnauthorized();
        $this->postJson('/api/vehicles')->assertUnauthorized();
    }

    private function createVehicle(User $user, array $overrides = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'user_id' => $user->id,
            'active' => true,
            'placa' => 'ABC1D23',
            'chassi' => '12345678901234567',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'versao' => 'XEi',
            'valor_venda' => 120000.00,
            'cor' => 'Branco',
            'km' => 15000,
            'cambio' => 'automatico',
            'combustivel' => 'flex',
        ], $overrides));
    }
}
