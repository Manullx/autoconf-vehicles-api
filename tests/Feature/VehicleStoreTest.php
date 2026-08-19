<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_vehicle_with_validated_data(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/vehicles', $this->validVehicle([
            'active' => false,
            'campo_desconhecido' => 'ignorado',
            'files' => [UploadedFile::fake()->image('vehicle.jpg')],
            'cover_index' => 0,
        ]), ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('active', true)
            ->assertJsonPath('created_by', $user->id)
            ->assertJsonPath('updated_by', $user->id)
            ->assertJsonPath('creator.id', $user->id)
            ->assertJsonPath('updater.id', $user->id)
            ->assertJsonPath('vehicle_images.0.is_cover', true)
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
                'files',
                'cover_index',
            ]);
    }

    public function test_store_rejects_duplicate_unique_fields(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/vehicles', $this->validVehicle([
            'files' => [UploadedFile::fake()->image('first.jpg')],
            'cover_index' => 0,
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/vehicles', $this->validVehicle([
            'placa' => 'abc1d23',
            'files' => [UploadedFile::fake()->image('second.jpg')],
            'cover_index' => 0,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['placa', 'chassi']);
    }

    public function test_store_creates_a_vehicle_with_up_to_five_images(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/vehicles', $this->validVehicle([
            'files' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.jpg'),
            ],
            'cover_index' => 1,
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(2, 'vehicle_images')
            ->assertJsonPath('vehicle_images.0.is_cover', false)
            ->assertJsonPath('vehicle_images.1.is_cover', true);

        foreach ($response->json('vehicle_images') as $image) {
            Storage::disk('public')->assertExists($image['path']);
        }
    }

    public function test_store_rejects_more_than_five_images_or_an_image_larger_than_2_mb(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/vehicles', $this->validVehicle([
            'files' => array_fill(0, 6, UploadedFile::fake()->image('vehicle.jpg')),
            'cover_index' => 0,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files']);

        $this->post('/api/vehicles', $this->validVehicle([
            'placa' => 'DEF4G56',
            'chassi' => '76543210987654321',
            'files' => [UploadedFile::fake()->image('large.jpg')->size(2049)],
            'cover_index' => 0,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_store_accepts_a_numeric_fifth_plate_character(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/vehicles', $this->validVehicle([
            'placa' => 'abc1223',
            'files' => [UploadedFile::fake()->image('vehicle.jpg')],
            'cover_index' => 0,
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('placa', 'ABC1223');
    }

    public function test_store_requires_a_valid_cover_index_when_images_are_sent(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $payload = $this->validVehicle([
            'files' => [UploadedFile::fake()->image('vehicle.jpg')],
        ]);

        $this->post('/api/vehicles', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_index']);

        $payload['cover_index'] = 1;

        $this->post('/api/vehicles', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_index']);
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
