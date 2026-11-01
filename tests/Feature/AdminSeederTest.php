<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Access;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_super_admin_and_keeps_its_password_on_reruns(): void
    {
        config(['app.admin.email' => 'boss@example.com', 'app.admin.password' => 'first-secret']);
        $this->seed(AdminSeeder::class);

        config(['app.admin.password' => 'second-secret']);
        $this->seed(AdminSeeder::class);

        $admin = User::sole();
        $this->assertSame('boss@example.com', $admin->email);
        $this->assertTrue($admin->hasRole(Access::SUPER_ADMIN));
        $this->assertFalse($admin->hasRole(Access::MEMBER));
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('first-secret', $admin->password));
    }

    public function test_outside_local_an_empty_password_is_random_not_password(): void
    {
        $this->app['env'] = 'production';
        config(['app.admin.email' => 'admin@example.com', 'app.admin.password' => null]);

        $this->app->make(AdminSeeder::class)->run(); // not db:seed, which asks "are you sure?" in production

        $this->assertFalse(Hash::check('password', User::sole()->password));
    }
}
