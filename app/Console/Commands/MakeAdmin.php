<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Access;
use Illuminate\Console\Command;

/** The first Super Admin is made from the server: php artisan metmemo:make-admin you@example.com */
class MakeAdmin extends Command
{
    protected $signature = 'metmemo:make-admin {email : The account to make a Super Admin}';

    protected $description = 'Give an existing account the Super Admin role';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account with that email. Sign up first, then run this again.');

            return self::FAILURE;
        }

        Access::install();
        $user->syncRoles([Access::SUPER_ADMIN]);
        $this->info("{$user->name} is now a Super Admin.");

        return self::SUCCESS;
    }
}
