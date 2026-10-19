<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    private Card $card;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->card = Card::factory()->create();
        $this->contact = Contact::factory()->for($this->card->user)->create(['card_id' => $this->card->id]);
        $this->contact->notes()->create(['body' => 'Hello']);

        $this->actingAs($this->card->user);
    }

    public function test_dashboard(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_cards_index(): void
    {
        $this->get(route('cards.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Cards/Index')->has('cards', 1));
    }

    public function test_cards_forms(): void
    {
        $this->get(route('cards.create'))->assertOk();
        $this->get(route('cards.edit', $this->card))->assertOk();
    }

    public function test_contacts_index(): void
    {
        $this->get(route('contacts.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Contacts/Index')->has('contacts.data', 1));
    }

    public function test_contacts_show_and_forms(): void
    {
        $this->get(route('contacts.create'))->assertOk();
        $this->get(route('contacts.show', $this->contact))->assertOk();
        $this->get(route('contacts.edit', $this->contact))->assertOk();
    }
}
