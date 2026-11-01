<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Access;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The Super Admin account (Administration: Overview, Users, Roles).
 *
 * Login: ADMIN_EMAIL (admin@example.com) with ADMIN_PASSWORD. Locally that defaults to "password";
 * anywhere else an empty ADMIN_PASSWORD gets a random one, printed once, so a live site never has a
 * guessable admin. Running it again keeps the account's password and only makes sure of the role.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Access::install();

        $email = config('app.admin.email');
        $user = User::firstWhere('email', $email);

        if (! $user) {
            $password = config('app.admin.password')
                ?: (app()->environment('local', 'testing') ? 'password' : Str::password(20, symbols: false));

            $user = User::query()->create([
                'name' => 'MetMemo Admin',
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            if (! config('app.admin.password')) {
                $this->command?->warn("Admin login: {$email} / {$password}  (shown once: save it now)");
            }
        }

        $user->syncRoles([Access::SUPER_ADMIN]);
    }
}
