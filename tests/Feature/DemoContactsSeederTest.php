<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\DemoContactsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoContactsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function demoUser(): User
    {
        return User::firstWhere('email', DemoContactsSeeder::EMAIL);
    }

    public function test_it_seeds_a_demo_account_the_assistant_can_be_shown_with(): void
    {
        $this->seed(DemoContactsSeeder::class);

        $contacts = $this->demoUser()->contacts()->withCount('notes')->get();

        $this->assertCount(25, $contacts);
        $this->assertGreaterThanOrEqual(40, $contacts->sum('notes_count')); // enough facts for a 20-question golden set
        // Something overdue, something due today, something upcoming, whatever day it is run.
        $this->assertTrue($contacts->contains(fn (Contact $c) => $c->next_follow_up_at?->lt(today())));
        $this->assertTrue($contacts->contains(fn (Contact $c) => $c->next_follow_up_at?->isToday()));
        $this->assertTrue($contacts->contains(fn (Contact $c) => $c->next_follow_up_at?->gt(today())));
        // A contact with nothing to go on (the "missing knowledge" case).
        $this->assertSame(0, $contacts->firstWhere('full_name', 'Isabel Navarro')->notes_count);
    }

    public function test_notes_are_dated_after_the_meeting_they_describe(): void
    {
        $this->seed(DemoContactsSeeder::class);

        foreach ($this->demoUser()->contacts()->with('notes')->get() as $contact) {
            foreach ($contact->notes as $note) {
                $this->assertTrue($note->created_at->startOfDay()->gte($contact->met_at), "{$contact->full_name}'s note predates the meeting");
            }
        }
    }

    public function test_running_it_again_replaces_the_demo_contacts_and_leaves_other_accounts_alone(): void
    {
        $someoneElse = Contact::factory()->create();

        $this->seed(DemoContactsSeeder::class);
        $this->seed(DemoContactsSeeder::class);

        $this->assertSame(25, $this->demoUser()->contacts()->count());
        $this->assertSame(1, User::where('email', DemoContactsSeeder::EMAIL)->count());
        $this->assertModelExists($someoneElse);
    }

    public function test_it_reindexes_the_demo_notes_when_indexing_is_on(): void
    {
        config(['services.ai.index_notes' => true, 'services.ai.url' => 'http://ai.test', 'services.ai.token' => 'secret']);
        Http::fake(['ai.test/*' => Http::response(status: 204)]);

        $this->seed(DemoContactsSeeder::class);

        $user = $this->demoUser();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/notes/forget') && $request->data() === ['user_id' => $user->id]);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/notes/index')
            && collect($request['notes'])->every(fn ($note) => $note['user_id'] === $user->id));
    }
}
