<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        ];

        $validator = Validator::make($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'Invalid initial administrator configuration: '.$validator->errors()->toJson()
            );
        }

        $user = User::firstOrNew(['email' => $credentials['email']]);
        $temporaryPassword = null;

        if (! $user->exists) {
            $temporaryPassword = Str::password(16);
            $user->password = $temporaryPassword;
        }

        $user->fill([
            'name' => $credentials['name'],
            'is_admin' => true,
            'first_login' => true,
        ])->save();

        $this->command?->info("Initial administrator ready: {$user->email}");

        if ($temporaryPassword !== null) {
            $this->command?->warn("Temporary administrator password: {$temporaryPassword}");
        }
    }
}
