<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the Super Admin and the demo owner (a real-estate broker) with one card and realistic contacts and notes.
     * Logins: admin@example.com (see AdminSeeder for the password) and test@example.com / password
     */
    public function run(): void
    {
        $this->call([AdminSeeder::class, DemoContactsSeeder::class]);
    }
}
