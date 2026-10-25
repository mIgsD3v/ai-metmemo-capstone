<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo owner (a real-estate broker) with one card and realistic contacts and notes.
     * Login: test@example.com / password
     */
    public function run(): void
    {
        $this->call(DemoContactsSeeder::class);
    }
}
