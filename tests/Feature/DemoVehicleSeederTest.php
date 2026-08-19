<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoVehicleSeeder;
use Database\Seeders\InitialAdminSeeder;
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

    public function test_initial_admin_seeder_preserves_password_and_resets_first_login(): void
    {
        config([
            'initial_admin.name' => 'Administrator',
            'initial_admin.email' => 'admin@example.com',
        ]);

        $this->seed(InitialAdminSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $password = $admin->password;
        $admin->update(['first_login' => false]);

        $this->seed(InitialAdminSeeder::class);

        $admin->refresh();

        $this->assertSame($password, $admin->password);
        $this->assertTrue($admin->first_login);
        $this->assertTrue($admin->is_admin);
    }
}
