<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleImageRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_images_and_first_image_becomes_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/vehicles/{$vehicle->id}/images", [
            'files' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.png'),
            ],
        ])->assertCreated()
            ->assertJsonCount(2)
            ->assertJsonPath('0.is_cover', true)
            ->assertJsonPath('1.is_cover', false);

        foreach ($response->json() as $image) {
            Storage::disk('public')->assertExists($image['path']);
        }
    }

    public function test_upload_requires_at_least_one_valid_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/images", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files']);

        $this->postJson("/api/vehicles/{$vehicle->id}/images", [
            'files' => [UploadedFile::fake()->create('document.txt')],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_owner_can_change_the_cover_image(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $first = $this->createImage($vehicle, 'vehicles/1/first.jpg', true);
        $second = $this->createImage($vehicle, 'vehicles/1/second.jpg', false);
        Sanctum::actingAs($user);

        $this->patchJson("/api/vehicles/{$vehicle->id}/images/{$second->id}/cover")
            ->assertOk()
            ->assertJsonPath('id', $second->id)
            ->assertJsonPath('is_cover', true);

        $this->assertFalse($first->refresh()->is_cover);
        $this->assertTrue($second->refresh()->is_cover);
    }

    public function test_cover_image_must_belong_to_the_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $otherVehicle = $this->createVehicle($user, [
            'placa' => 'DEF4G56',
            'chassi' => '76543210987654321',
        ]);
        $otherImage = $this->createImage($otherVehicle, 'vehicles/2/image.jpg', true);
        Sanctum::actingAs($user);

        $this->patchJson("/api/vehicles/{$vehicle->id}/images/{$otherImage->id}/cover")
            ->assertNotFound();
    }

    public function test_owner_can_delete_an_image_and_its_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        Storage::disk('public')->put('vehicles/1/image.jpg', 'image');
        $image = $this->createImage($vehicle, 'vehicles/1/image.jpg', true);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing($image->path);
        $this->assertDatabaseMissing('vehicle_image', ['id' => $image->id]);
    }

    public function test_regular_user_cannot_manage_another_users_images(): void
    {
        Storage::fake('public');
        $vehicle = $this->createVehicle(User::factory()->create());
        $image = $this->createImage($vehicle, 'vehicles/1/image.jpg', true);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/vehicles/{$vehicle->id}/images", [
            'files' => [UploadedFile::fake()->image('image.jpg')],
        ])->assertForbidden();
        $this->patchJson("/api/vehicles/{$vehicle->id}/images/{$image->id}/cover")->assertForbidden();
        $this->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}")->assertForbidden();
    }

    public function test_image_routes_require_authentication(): void
    {
        $this->postJson('/api/vehicles/1/images')->assertUnauthorized();
        $this->patchJson('/api/vehicles/1/images/1/cover')->assertUnauthorized();
        $this->deleteJson('/api/vehicles/1/images/1')->assertUnauthorized();
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

    private function createImage(Vehicle $vehicle, string $path, bool $isCover): VehicleImage
    {
        return $vehicle->vehicleImages()->create([
            'path' => $path,
            'is_cover' => $isCover,
        ]);
    }
}
