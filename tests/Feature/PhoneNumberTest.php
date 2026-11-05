<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_and_telephone_are_saved_in_international_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contacts.store'), [
            'full_name' => 'Juan Dela Cruz',
            'phone' => '0917 123 4567', // no country code: read as Philippine
            'telephone' => '+63 2 8123 4567',
        ])->assertSessionHasNoErrors();

        $contact = $user->contacts()->sole();
        $this->assertSame('+63 917 123 4567', $contact->phone);
        $this->assertSame('+63 2 8123 4567', $contact->telephone);
    }

    public function test_numbers_from_other_countries_are_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contacts.store'), [
            'full_name' => 'Kenji Watanabe',
            'phone' => '+81 90-1234-5678',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+81 90-1234-5678', $user->contacts()->sole()->phone);
    }

    public function test_a_number_that_does_not_fit_its_country_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contacts.store'), [
            'full_name' => 'Juan Dela Cruz',
            'phone' => '+63 917 12',
            'telephone' => 'call me',
        ])->assertSessionHasErrors([
            'phone' => 'Enter a valid mobile number for the selected country.',
            'telephone' => 'Enter a valid telephone number for the selected country.',
        ]);

        $this->assertSame(0, $user->contacts()->count());
    }

    public function test_cards_have_a_telephone_that_goes_into_the_vcard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cards.store'), [
            'full_name' => 'Maria Santos',
            'phone' => '09171234567',
            'telephone' => '(02) 8123 4567',
            'is_public' => true,
        ])->assertSessionHasNoErrors();

        $card = $user->cards()->sole();
        $this->assertSame('+63 917 123 4567', $card->phone);
        $this->assertSame('+63 2 8123 4567', $card->telephone);

        $vcard = $this->get(route('public-card.vcard', $card))->assertOk()->getContent();
        $this->assertStringContainsString("TEL;TYPE=CELL:+63 917 123 4567\r\n", $vcard);
        $this->assertStringContainsString("TEL;TYPE=WORK,VOICE:+63 2 8123 4567\r\n", $vcard);
    }

    public function test_the_lead_form_checks_the_visitors_number(): void
    {
        $card = Card::factory()->create();

        $this->post(route('public-card.leads.store', $card), ['full_name' => 'Maria Santos', 'phone' => '12345'])
            ->assertSessionHasErrors(['phone' => 'Enter a valid phone number for the selected country.']);

        $this->post(route('public-card.leads.store', $card), ['full_name' => 'Maria Santos', 'phone' => '0917 123 4567'])
            ->assertSessionHasNoErrors();

        $this->assertSame('+63 917 123 4567', $card->user->contacts()->sole()->phone);
    }

    public function test_swapping_cards_copies_the_telephone(): void
    {
        $maria = Card::factory()->create(['telephone' => '+63 2 8123 4567']);
        $ben = User::factory()->create();

        $this->actingAs($ben)->post(route('public-card.swap', $maria))->assertRedirect();

        $this->assertSame('+63 2 8123 4567', $ben->contacts()->sole()->telephone);
    }
}
