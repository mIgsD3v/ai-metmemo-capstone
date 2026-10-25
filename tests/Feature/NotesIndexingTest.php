<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotesIndexingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai.index_notes' => true, 'services.ai.url' => 'http://ai.test', 'services.ai.token' => 'secret']);
        $this->withoutDefer(); // run "after the response" work right away, so tests can see it
    }

    public function test_a_new_note_is_sent_to_the_index_with_its_owner(): void
    {
        Http::fake(['ai.test/notes/index' => Http::response(status: 204)]);
        $contact = Contact::factory()->create(['full_name' => 'Juan Dela Cruz']);

        $this->actingAs($contact->user)->post(route('contacts.notes.store', $contact), ['body' => 'Asked for a quote for 3 branches.']);

        $note = $contact->notes()->sole();
        Http::assertSent(fn (Request $request) => $request->url() === 'http://ai.test/notes/index'
            && $request->hasHeader('X-Internal-Token', 'secret')
            && $request['notes'] === [[
                'note_id' => $note->id,
                'user_id' => $contact->user_id,
                'contact_id' => $contact->id,
                'contact_name' => 'Juan Dela Cruz',
                'body' => 'Asked for a quote for 3 branches.',
                'created_at' => $note->created_at->toIso8601String(),
            ]]);
    }

    public function test_notes_from_the_lead_form_and_scanner_are_indexed_too(): void
    {
        Http::fake(['ai.test/*' => Http::response(status: 204)]);
        $card = Card::factory()->create();

        $this->post(route('public-card.leads.store', $card), ['full_name' => 'Maria Santos', 'email' => 'maria@example.com', 'message' => 'Call me about the warehouse.']);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/notes/index')
            && $request['notes'][0]['body'] === 'Message from card visitor: Call me about the warehouse.');
    }

    public function test_the_note_still_saves_when_the_ai_service_is_down(): void
    {
        Http::fake(['ai.test/*' => Http::failedConnection()]);
        Log::spy();
        $contact = Contact::factory()->create();

        $this->actingAs($contact->user)
            ->post(route('contacts.notes.store', $contact), ['body' => 'Private: wants a discount.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Private: wants a discount.', $contact->notes()->sole()->body);
        // The failure is logged without the note's text.
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => str_contains($message, 'could not index')
            && $context['count'] === 1
            && ! str_contains(json_encode($context), 'discount'));
    }

    public function test_deleting_a_contact_removes_their_notes_from_the_index(): void
    {
        Http::fake(['ai.test/*' => Http::response(status: 204)]);
        $contact = Contact::factory()->create();

        $this->actingAs($contact->user)->delete(route('contacts.destroy', $contact));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/notes/forget')
            && $request->data() === ['user_id' => $contact->user_id, 'contact_id' => $contact->id]);
    }

    public function test_deleting_an_account_removes_all_its_notes_from_the_index(): void
    {
        Http::fake(['ai.test/*' => Http::response(status: 204)]);
        $user = User::factory()->create();

        app(\Laravel\Jetstream\Contracts\DeletesUsers::class)->delete($user);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/notes/forget')
            && $request->data() === ['user_id' => $user->id]);
    }

    public function test_index_command_sends_every_note_in_batches(): void
    {
        config(['services.ai.index_notes' => false]); // seed quietly, then index on demand
        $contacts = Contact::factory()->count(3)->create();
        foreach ($contacts as $contact) {
            foreach (range(1, 40) as $n) {
                $contact->notes()->create(['body' => "Note {$n}"]);
            }
        }
        config(['services.ai.index_notes' => true]);
        Http::fake(['ai.test/*' => Http::response(status: 204)]);

        $this->artisan('metmemo:index-notes')->expectsOutput('Indexed 120 of 120 notes.')->assertSuccessful();

        Http::assertSentCount(2); // 100 + 20
    }

    public function test_index_command_can_target_one_user_and_reports_failures(): void
    {
        config(['services.ai.index_notes' => false]);
        $mine = Contact::factory()->create();
        $mine->notes()->create(['body' => 'Mine']);
        Contact::factory()->create()->notes()->create(['body' => 'Someone else']);
        config(['services.ai.index_notes' => true]);
        Http::fake(['ai.test/*' => Http::response(['detail' => 'Notes could not be processed right now'], 502)]);

        $this->artisan('metmemo:index-notes', ['--user' => $mine->user_id])
            ->expectsOutput('Indexed 0 of 1 notes.')
            ->assertFailed();
    }

    public function test_nothing_is_sent_when_indexing_is_turned_off(): void
    {
        config(['services.ai.index_notes' => false]);
        Http::fake();
        $contact = Contact::factory()->create();

        $this->actingAs($contact->user)->post(route('contacts.notes.store', $contact), ['body' => 'Hello']);

        Http::assertNothingSent();
    }
}
