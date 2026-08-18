<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_only_lists_own_vehicles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownVehicle = $this->createVehicle($user, 'ABC1D23', '12345678901234567');
        $this->createVehicle($otherUser, 'DEF4G56', '76543210987654321');

        Sanctum::actingAs($user);

        $this->getJson('/api/vehicles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownVehicle->id);
    }

    public function test_regular_user_cannot_access_another_users_vehicle(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $vehicle = $this->createVehicle($otherUser, 'ABC1D23', '12345678901234567');

        Sanctum::actingAs($user);

        $this->getJson("/api/vehicles/{$vehicle->id}")->assertForbidden();
    }

    public function test_admin_can_access_all_vehicles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $vehicle = $this->createVehicle($firstUser, 'ABC1D23', '12345678901234567');
        $this->createVehicle($secondUser, 'DEF4G56', '76543210987654321');

        Sanctum::actingAs($admin);

        $this->getJson('/api/vehicles')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/vehicles/{$vehicle->id}")->assertOk();
    }

    private function createVehicle(User $user, string $placa, string $chassi): Vehicle
    {
        return Vehicle::create([
            'user_id' => $user->id,
            'active' => true,
            'placa' => $placa,
            'chassi' => $chassi,
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'versao' => 'XEi',
            'valor_venda' => 120000.00,
            'cor' => 'Branco',
            'km' => 15000,
            'cambio' => 'automatico',
            'combustivel' => 'flex',
        ]);
    }
}
