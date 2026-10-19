<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_id' => User::factory(),
            'full_name' => $name,
            'job_title' => fake()->jobTitle(),
            'company' => fake()->company(),
            'bio' => fake()->sentence(16),
            'email' => fake()->safeEmail(),
            'phone' => '+63 9'.fake()->numerify('## ### ####'),
            'website' => fake()->url(),
            'location' => fake()->randomElement(['Makati City', 'Quezon City', 'Cebu City', 'Davao City', 'Pasig City']),
            'is_public' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(['is_public' => false]);
    }
}
