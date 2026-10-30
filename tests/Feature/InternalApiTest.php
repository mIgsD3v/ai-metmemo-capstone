<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-internal-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai.token' => self::TOKEN]);
    }

    private function internal(string $uri, string $token = self::TOKEN)
    {
        return $this->getJson($uri, ['X-Internal-Token' => $token]);
    }

    public function test_request_without_token_is_refused(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('internal.followups', $user))->assertUnauthorized();
    }

    public function test_request_with_wrong_token_is_refused(): void
    {
        $user = User::factory()->create();

        $this->internal(route('internal.followups', $user), 'wrong-token')->assertUnauthorized();
    }

    public function test_everything_is_refused_when_no_token_is_configured(): void
    {
        config(['services.ai.token' => null]);
        $user = User::factory()->create();

        $this->internal(route('internal.followups', $user), '')->assertUnauthorized();
    }

    public function test_followups_returns_overdue_and_upcoming_within_window_only(): void
    {
        $this->travelTo('2026-10-19');
        $user = User::factory()->create();
        $overdue = Contact::factory()->for($user)->create(['next_follow_up_at' => '2026-10-16']);
        $thisWeek = Contact::factory()->for($user)->create(['next_follow_up_at' => '2026-10-24']);
        Contact::factory()->for($user)->create(['next_follow_up_at' => '2026-11-10']);
        Contact::factory()->for($user)->create(['next_follow_up_at' => null]);

        $response = $this->internal(route('internal.followups', [$user, 'days' => 7]))->assertOk();

        $this->assertSame([$overdue->id, $thisWeek->id], $response->json('contacts.*.id'));
        $this->assertSame(3, $response->json('contacts.0.days_overdue'));
        $this->assertSame(0, $response->json('contacts.1.days_overdue'));
        $this->assertArrayNotHasKey('email', $response->json('contacts.0'));
    }

    public function test_followups_never_include_another_users_contacts(): void
    {
        $user = User::factory()->create();
        Contact::factory()->create(['next_follow_up_at' => today()]);

        $this->internal(route('internal.followups', $user))
            ->assertOk()
            ->assertJsonCount(0, 'contacts');
    }

    public function test_followups_rejects_an_unreasonable_window(): void
    {
        $user = User::factory()->create();

        $this->internal(route('internal.followups', [$user, 'days' => 999]))->assertUnprocessable();
    }

    public function test_contact_search_only_matches_the_users_own_contacts(): void
    {
        $user = User::factory()->create();
        $mine = Contact::factory()->for($user)->create(['full_name' => 'Juan Dela Cruz']);
        Contact::factory()->for($user)->create(['full_name' => 'Maria Santos', 'company' => 'Acme', 'email' => 'maria@example.com', 'event' => 'Expo']);
        Contact::factory()->create(['full_name' => 'Juan Someone Else']);

        $response = $this->internal(route('internal.contacts', [$user, 'search' => 'Juan']))->assertOk();

        $this->assertSame([$mine->id], $response->json('contacts.*.id'));
    }

    public function test_contact_search_matches_each_word_in_any_order_and_case(): void
    {
        $user = User::factory()->create();
        $raffy = Contact::factory()->for($user)->create(['full_name' => 'Rafael "Raffy" Mendoza']);
        Contact::factory()->for($user)->create(['full_name' => 'Liza Mendoza']);

        $response = $this->internal(route('internal.contacts', [$user, 'search' => 'raffy mendoza']))->assertOk();

        $this->assertSame([$raffy->id], $response->json('contacts.*.id'));
    }

    public function test_contact_search_requires_a_query(): void
    {
        $user = User::factory()->create();

        $this->internal(route('internal.contacts', $user))->assertUnprocessable();
    }

    public function test_notes_returns_the_contacts_notes_newest_first(): void
    {
        $contact = Contact::factory()->create();
        $this->travelTo('2026-10-10');
        $older = $contact->notes()->create(['body' => 'Met at the expo.']);
        $this->travelTo('2026-10-17');
        $newer = $contact->notes()->create(['body' => 'Asked for a quote for 3 branches.']);

        $response = $this->internal(route('internal.notes', [$contact->user, $contact]))->assertOk();

        $this->assertSame($contact->id, $response->json('contact.id'));
        $this->assertSame([$newer->id, $older->id], $response->json('notes.*.id'));
        $this->assertSame('Asked for a quote for 3 branches.', $response->json('notes.0.body'));
    }

    public function test_cannot_read_another_users_contact_notes(): void
    {
        $user = User::factory()->create();
        $someoneElses = Contact::factory()->create();
        $someoneElses->notes()->create(['body' => 'Private.']);

        $this->internal(route('internal.notes', [$user, $someoneElses]))->assertNotFound();
    }
}
