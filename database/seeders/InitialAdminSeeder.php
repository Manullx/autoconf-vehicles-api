<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    /**
     * Seed the application's initial administrator.
     */
    public function run(): void
    {
        $credentials = [
            'name' => config('initial_admin.name'),
            'email' => config('initial_admin.email'),
            'password' => config('initial_admin.password'),
        ];

        $validator = Validator::make($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'Invalid initial administrator configuration: '.$validator->errors()->toJson()
            );
        }

        $user = User::updateOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'password' => $credentials['password'],
                'is_admin' => true,
            ],
        );

        $this->command?->info("Initial administrator ready: {$user->email}");
    }
}
