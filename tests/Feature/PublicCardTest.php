<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_card_page_hides_owner_account(): void
    {
        $card = Card::factory()->create();

        $this->get(route('public-card.show', $card))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Card')
                ->where('card.full_name', $card->full_name)
                ->missing('card.user_id')
                ->missing('card.user')
                ->where('card.photo_url', null));
    }

    public function test_public_card_shows_owner_photo(): void
    {
        $card = Card::factory()->create();
        $card->user->forceFill(['profile_photo_path' => 'profile-photos/me.jpg'])->save();

        $this->get(route('public-card.show', $card))
            ->assertInertia(fn ($page) => $page
                ->where('card.photo_url', fn ($url) => str_ends_with($url, '/storage/profile-photos/me.jpg')));
    }

    public function test_hidden_card_shows_the_branded_card_error_page(): void
    {
        $card = Card::factory()->private()->create();

        $this->get(route('public-card.show', $card))
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('context', 'card'));
    }

    public function test_hidden_and_missing_cards_look_the_same(): void
    {
        $hidden = Card::factory()->private()->create();

        $hiddenPage = $this->get(route('public-card.show', $hidden))->assertNotFound();
        $missingPage = $this->get(route('public-card.show', Str::uuid()->toString()))->assertNotFound();

        $this->assertSame(
            $hiddenPage->viewData('page')['props']['context'],
            $missingPage->viewData('page')['props']['context'],
        );
    }

    public function test_guessed_or_malformed_card_links_are_not_found(): void
    {
        // A valid UUID that is not a card, and old-style name links, all 404 with the card message.
        foreach (['/c/'.Str::uuid(), '/c/maria-santos', '/c/1'] as $url) {
            $this->get($url)
                ->assertNotFound()
                ->assertInertia(fn ($page) => $page->component('Error')->where('context', 'card'));
        }
    }

    public function test_card_and_contact_routes_only_accept_uuids(): void
    {
        // Checked on the route itself: SQLite (tests) accepts any string, PostgreSQL would error.
        foreach (['public-card.show', 'public-card.vcard', 'cards.edit', 'cards.print'] as $name) {
            $this->assertNotEmpty(Route::getRoutes()->getByName($name)->wheres['card'] ?? null, "{$name} has no UUID rule");
        }
        foreach (['contacts.show', 'contacts.edit', 'contacts.notes.store'] as $name) {
            $this->assertNotEmpty(Route::getRoutes()->getByName($name)->wheres['contact'] ?? null, "{$name} has no UUID rule");
        }
    }

    public function test_contact_urls_need_a_uuid(): void
    {
        $this->actingAs(User::factory()->create())->get('/contacts/1')->assertNotFound();
    }

    public function test_unknown_url_shows_the_branded_404_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('context', null));
    }

    public function test_private_card_returns_404(): void
    {
        $card = Card::factory()->private()->create();

        $this->get(route('public-card.show', $card))->assertNotFound();
        $this->get(route('public-card.vcard', $card))->assertNotFound();
    }

    public function test_vcard_download(): void
    {
        $card = Card::factory()->create(['full_name' => 'Maria Santos', 'company' => 'Santos, Realty']);

        $response = $this->get(route('public-card.vcard', $card))->assertOk();

        $this->assertStringContainsString('text/vcard', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('FN:Maria Santos', $response->getContent());
        $this->assertStringContainsString('ORG:Santos\, Realty', $response->getContent());
    }

    public function test_visitor_lead_becomes_contact_for_card_owner(): void
    {
        $card = Card::factory()->create();

        $this->post(route('public-card.leads.store', $card), [
            'full_name' => 'Visitor One',
            'phone' => '+63 917 123 4567',
            'message' => 'Interested in rental management.',
        ])->assertRedirect();

        $contact = $card->user->contacts()->first();
        $this->assertSame('Visitor One', $contact->full_name);
        $this->assertSame('lead_form', $contact->source->value);
        $this->assertSame($card->id, $contact->card_id);
        $this->assertNotNull($contact->next_follow_up_at);
        $this->assertStringContainsString('rental management', $contact->notes()->first()->body);
    }

    public function test_lead_needs_email_or_phone(): void
    {
        $card = Card::factory()->create();

        $this->post(route('public-card.leads.store', $card), ['full_name' => 'No Contact Info'])
            ->assertSessionHasErrors(['email', 'phone']);
    }
}
