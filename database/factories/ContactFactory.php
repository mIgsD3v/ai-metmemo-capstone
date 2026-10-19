<?php

namespace Database\Factories;

use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        $metAt = fake()->dateTimeBetween('-45 days', 'now');

        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+63 9'.fake()->numerify('## ### ####'),
            'company' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'source' => fake()->randomElement(ContactSource::cases()),
            'tags' => fake()->randomElements(['hot-lead', 'investor', 'condo', 'partner', 'referral', 'enterprise'], 2),
            'event' => fake()->randomElement(['PhilConstruct 2026', 'Manila Startup Week', 'BGC Networking Night', 'Real Estate Expo SMX']),
            'met_at' => $metAt,
            'next_follow_up_at' => fake()->dateTimeBetween($metAt, '+14 days'),
        ];
    }
}
