<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a demo owner with one card and some synthetic contacts.
     * Login: test@example.com / password
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $card = Card::factory()->for($user)->create([
            'full_name' => 'Maria Santos',
            'job_title' => 'Licensed Real Estate Broker',
            'company' => 'Santos Realty',
            'bio' => 'Helping first-time buyers find condos in Makati and BGC since 2015.',
            'location' => 'Makati City',
        ]);

        Contact::factory(15)->for($user)->create(['card_id' => $card->id])
            ->each(fn (Contact $contact) => $contact->notes()->create([
                'body' => fake()->randomElement([
                    'Interested in a 1BR condo near BGC, budget around 6M.',
                    'Asked about rental management for two units in Makati.',
                    'Wants to compare Pag-IBIG and bank loan options. Send a rate sheet.',
                    'Met briefly at the booth, asked for the price list.',
                ]),
            ]));
    }
}
