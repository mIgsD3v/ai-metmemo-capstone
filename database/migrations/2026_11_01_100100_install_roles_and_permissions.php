<?php

use App\Models\User;
use App\Support\Access;
use Illuminate\Database\Migrations\Migration;

// The permissions and built-in roles, and every existing account becomes a Member (same access as before).
return new class extends Migration
{
    public function up(): void
    {
        Access::install();

        User::query()->whereDoesntHave('roles')->each(fn (User $user) => $user->assignRole(Access::MEMBER));
    }

    public function down(): void
    {
        // The tables themselves are dropped by the previous migration's rollback.
    }
};
