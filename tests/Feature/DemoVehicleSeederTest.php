<?php

namespace Tests\Feature;

use Database\Seeders\DemoVehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoVehicleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_ten_vehicles_with_placeholder_images_and_is_idempotent(): void
    {
        Storage::fake('public');
        config([
            'initial_admin.name' => 'Administrator',
            'initial_admin.email' => 'admin@example.com',
            'initial_admin.password' => 'secure-password',
        ]);

        $this->seed();
        $this->seed(DemoVehicleSeeder::class);

        $this->assertDatabaseCount('vehicles', 10);
        $this->assertDatabaseCount('vehicle_image', 10);

        foreach (Storage::disk('public')->allFiles('vehicles') as $path) {
            Storage::disk('public')->assertExists($path);
        }

        $this->assertCount(10, Storage::disk('public')->allFiles('vehicles'));
    }
}
