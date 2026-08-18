<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_vehicle_with_validated_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/vehicles', $this->validVehicle([
            'active' => false,
            'campo_desconhecido' => 'ignorado',
        ]));

        $response->assertCreated()
            ->assertJsonPath('active', true)
            ->assertJsonMissingPath('campo_desconhecido');

        $this->assertDatabaseHas('vehicles', [
            'placa' => 'ABC1D23',
            'active' => true,
        ]);
    }

    public function test_store_rejects_missing_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/vehicles', ['marca' => 'Toyota'])
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

    public function test_store_rejects_duplicate_unique_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = $this->validVehicle();
        $this->postJson('/api/vehicles', $payload)->assertCreated();

        $this->postJson('/api/vehicles', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['placa', 'chassi']);
    }

    private function validVehicle(array $overrides = []): array
    {
        return array_merge([
            'placa' => 'ABC1D23',
            'chassi' => '12345678901234567',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'versao' => 'XEi',
            'valor_venda' => '120000.00',
            'cor' => 'Branco',
            'km' => 15000,
            'cambio' => 'automatico',
            'combustivel' => 'flex',
        ], $overrides);
    }
}
