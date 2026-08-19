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

        $this->assertSame($user->id, $vehicle->refresh()->updated_by);
        $this->assertSame(1, $vehicle->vehicleImages()->where('is_cover', true)->count());
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

        $this->postJson("/api/vehicles/{$vehicle->id}/images", [
            'files' => [UploadedFile::fake()->image('large.jpg')->size(2049)],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_upload_cannot_exceed_five_total_vehicle_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        foreach (range(1, 5) as $index) {
            $this->createImage($vehicle, "vehicles/1/{$index}.jpg", $index === 1);
        }

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/images", [
            'files' => [UploadedFile::fake()->image('sixth.jpg')],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['files']);

        $this->assertSame(5, $vehicle->vehicleImages()->count());
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
        $this->assertSame($user->id, $vehicle->refresh()->updated_by);
    }

    public function test_selecting_the_current_cover_keeps_exactly_one_cover(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $cover = $this->createImage($vehicle, 'vehicles/1/cover.jpg', true);
        $this->createImage($vehicle, 'vehicles/1/other.jpg', false);
        Sanctum::actingAs($user);

        $this->patchJson("/api/vehicles/{$vehicle->id}/images/{$cover->id}/cover")
            ->assertOk()
            ->assertJsonPath('id', $cover->id)
            ->assertJsonPath('is_cover', true);

        $this->assertSame(1, $vehicle->vehicleImages()->where('is_cover', true)->count());
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
        $replacement = $this->createImage($vehicle, 'vehicles/1/replacement.jpg', false);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing($image->path);
        $this->assertDatabaseMissing('vehicle_image', ['id' => $image->id]);
        $this->assertTrue($replacement->refresh()->is_cover);
        $this->assertSame(1, $vehicle->vehicleImages()->where('is_cover', true)->count());
        $this->assertSame($user->id, $vehicle->refresh()->updated_by);
    }

    public function test_owner_cannot_delete_the_only_vehicle_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        Storage::disk('public')->put('vehicles/1/image.jpg', 'image');
        $image = $this->createImage($vehicle, 'vehicles/1/image.jpg', true);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        Storage::disk('public')->assertExists($image->path);
        $this->assertDatabaseHas('vehicle_image', [
            'id' => $image->id,
            'is_cover' => true,
        ]);
    }

    public function test_deleting_a_non_cover_image_preserves_the_current_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $cover = $this->createImage($vehicle, 'vehicles/1/cover.jpg', true);
        Storage::disk('public')->put('vehicles/1/other.jpg', 'image');
        $other = $this->createImage($vehicle, 'vehicles/1/other.jpg', false);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}/images/{$other->id}")
            ->assertNoContent();

        $this->assertTrue($cover->refresh()->is_cover);
        $this->assertSame(1, $vehicle->vehicleImages()->where('is_cover', true)->count());
    }

    public function test_admin_image_change_updates_audit_without_changing_owner_or_creator(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $vehicle = $this->createVehicle($owner);
        $first = $this->createImage($vehicle, 'vehicles/1/first.jpg', true);
        $second = $this->createImage($vehicle, 'vehicles/1/second.jpg', false);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/vehicles/{$vehicle->id}/images/{$second->id}/cover")
            ->assertOk();

        $vehicle->refresh();

        $this->assertSame($owner->id, $vehicle->user_id);
        $this->assertSame($owner->id, $vehicle->created_by);
        $this->assertSame($admin->id, $vehicle->updated_by);
        $this->assertFalse($first->refresh()->is_cover);
        $this->assertTrue($second->refresh()->is_cover);
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
            'created_by' => $user->id,
            'updated_by' => $user->id,
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
