<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoVehicleSeeder extends Seeder
{
    /**
     * Seed ten example vehicles with local placeholder images.
     */
    public function run(): void
    {
        $owner = User::where('email', config('initial_admin.email'))->first();

        if (! $owner) {
            throw new RuntimeException('Run InitialAdminSeeder before DemoVehicleSeeder.');
        }

        foreach ($this->vehicles() as $index => $attributes) {
            $vehicle = Vehicle::updateOrCreate(
                ['placa' => $attributes['placa']],
                [
                    'user_id' => $owner->id,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'active' => true,
                    ...$attributes,
                ],
            );

            $path = "vehicles/{$vehicle->id}/placeholder.svg";
            Storage::disk('public')->put($path, $this->placeholder($attributes['marca'], $attributes['modelo']));
            $vehicle->vehicleImages()->updateOrCreate(
                ['path' => $path],
                ['is_cover' => true],
            );

            $this->command?->line(sprintf('%d/10 %s %s', $index + 1, $attributes['marca'], $attributes['modelo']));
        }
    }

    /**
     * @return list<array<string, int|float|string>>
     */
    private function vehicles(): array
    {
        return [
            ['placa' => 'ABC1D23', 'chassi' => '9BWZZZ377VT000001', 'marca' => 'Toyota', 'modelo' => 'Corolla', 'versao' => 'XEi', 'valor_venda' => 120000.00, 'cor' => 'Branco', 'km' => 15000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'DEF4G56', 'chassi' => '9BWZZZ377VT000002', 'marca' => 'Honda', 'modelo' => 'Civic', 'versao' => 'Touring', 'valor_venda' => 145000.00, 'cor' => 'Preto', 'km' => 22000, 'cambio' => 'automatico', 'combustivel' => 'gasolina'],
            ['placa' => 'GHI7J89', 'chassi' => '9BWZZZ377VT000003', 'marca' => 'Volkswagen', 'modelo' => 'T-Cross', 'versao' => 'Comfortline', 'valor_venda' => 110000.00, 'cor' => 'Cinza', 'km' => 31000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'JKL0M12', 'chassi' => '9BWZZZ377VT000004', 'marca' => 'Chevrolet', 'modelo' => 'Onix', 'versao' => 'Premier', 'valor_venda' => 85000.00, 'cor' => 'Vermelho', 'km' => 18000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'NOP3Q45', 'chassi' => '9BWZZZ377VT000005', 'marca' => 'Hyundai', 'modelo' => 'Creta', 'versao' => 'Limited', 'valor_venda' => 135000.00, 'cor' => 'Prata', 'km' => 27000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'RST6U78', 'chassi' => '9BWZZZ377VT000006', 'marca' => 'Jeep', 'modelo' => 'Renegade', 'versao' => 'Longitude', 'valor_venda' => 105000.00, 'cor' => 'Verde', 'km' => 35000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'VWX9Y01', 'chassi' => '9BWZZZ377VT000007', 'marca' => 'Fiat', 'modelo' => 'Pulse', 'versao' => 'Drive', 'valor_venda' => 98000.00, 'cor' => 'Azul', 'km' => 12000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'ZAB2C34', 'chassi' => '9BWZZZ377VT000008', 'marca' => 'Nissan', 'modelo' => 'Kicks', 'versao' => 'Advance', 'valor_venda' => 112000.00, 'cor' => 'Branco', 'km' => 29000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'EFG5H67', 'chassi' => '9BWZZZ377VT000009', 'marca' => 'Renault', 'modelo' => 'Duster', 'versao' => 'Iconic', 'valor_venda' => 103000.00, 'cor' => 'Marrom', 'km' => 41000, 'cambio' => 'automatico', 'combustivel' => 'flex'],
            ['placa' => 'IJK8L90', 'chassi' => '9BWZZZ377VT000010', 'marca' => 'BYD', 'modelo' => 'Dolphin', 'versao' => 'GS', 'valor_venda' => 149000.00, 'cor' => 'Azul', 'km' => 8000, 'cambio' => 'automatico', 'combustivel' => 'eletrico'],
        ];
    }

    private function placeholder(string $brand, string $model): string
    {
        $label = htmlspecialchars("{$brand} {$model}", ENT_XML1);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
            <rect width="800" height="450" fill="#e5e7eb"/>
            <text x="400" y="225" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="36" fill="#374151">{$label}</text>
        </svg>
        SVG;
    }
}
