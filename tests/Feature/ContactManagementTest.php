<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_contact_with_comma_separated_tags(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contacts.store'), [
            'full_name' => 'Juan Dela Cruz',
            'tags' => 'investor, condo, ',
            'next_follow_up_at' => '2026-10-20',
        ])->assertRedirect();

        $contact = $user->contacts()->first();
        $this->assertSame(['investor', 'condo'], $contact->tags);
        $this->assertSame('manual', $contact->source->value);
    }

    public function test_cannot_link_contact_to_another_users_card(): void
    {
        $user = User::factory()->create();
        $otherCard = Card::factory()->create();

        $this->actingAs($user)->post(route('contacts.store'), [
            'full_name' => 'Juan Dela Cruz',
            'card_id' => $otherCard->id,
        ])->assertSessionHasErrors('card_id');
    }

    public function test_owner_can_add_note(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($contact->user)
            ->post(route('contacts.notes.store', $contact), ['body' => 'Wants a 2BR in BGC.'])
            ->assertRedirect();

        $this->assertSame('Wants a 2BR in BGC.', $contact->notes()->first()->body);
    }

    public function test_marking_followed_up_records_today_and_sets_the_next_date(): void
    {
        $this->travelTo('2026-10-21 09:00');
        $contact = Contact::factory()->create(['next_follow_up_at' => '2026-10-20', 'last_contacted_at' => null]);

        $this->actingAs($contact->user)
            ->post(route('contacts.followed-up', $contact), ['next_in_days' => 7])
            ->assertRedirect()
            ->assertSessionHas('flash.banner', "Marked {$contact->full_name} as followed up. Next follow-up: Oct 28.");

        $contact->refresh();
        $this->assertSame('2026-10-21', $contact->last_contacted_at->toDateString());
        $this->assertSame('2026-10-28', $contact->next_follow_up_at->toDateString());
        $this->assertFalse(app(\App\Services\ContactService::class)->dueForFollowUp($contact->user)->contains($contact));
    }

    public function test_marking_followed_up_with_no_more_follow_ups_clears_the_date(): void
    {
        $contact = Contact::factory()->create(['next_follow_up_at' => today()]);

        $this->actingAs($contact->user)->post(route('contacts.followed-up', $contact), ['next_in_days' => null]);

        $this->assertNull($contact->fresh()->next_follow_up_at);
        $this->assertNotNull($contact->fresh()->last_contacted_at);
    }

    public function test_follow_up_only_offers_the_listed_intervals(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($contact->user)
            ->post(route('contacts.followed-up', $contact), ['next_in_days' => 9999])
            ->assertSessionHasErrors('next_in_days');
    }

    public function test_cannot_mark_someone_elses_contact_as_followed_up(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('contacts.followed-up', $contact), ['next_in_days' => 7])
            ->assertForbidden();
    }

    public function test_user_cannot_view_someone_elses_contact(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('contacts.show', $contact))
            ->assertForbidden();
    }

    public function test_search_only_returns_own_contacts(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['full_name' => 'Ana Reyes']);
        Contact::factory()->create(['full_name' => 'Ana Lim']);

        $this->actingAs($user)
            ->get(route('contacts.index', ['search' => 'Ana']))
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.full_name', 'Ana Reyes'));
    }

    public function test_new_contact_can_be_saved_with_a_first_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contacts.store'), [
            'full_name' => 'Juan Dela Cruz',
            'tags' => ['investor', 'expo'],
            'note' => 'Wants a quote for 3 branches.',
        ])->assertRedirect();

        $contact = $user->contacts()->first();
        $this->assertSame(['investor', 'expo'], $contact->tags);
        $this->assertSame('Wants a quote for 3 branches.', $contact->notes()->first()->body);
    }

    public function test_editing_a_contact_never_adds_a_note(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($contact->user)->put(route('contacts.update', $contact), [
            'full_name' => 'Renamed',
            'note' => 'Should be ignored on edit.',
        ])->assertRedirect();

        $this->assertSame('Renamed', $contact->fresh()->full_name);
        $this->assertSame(0, $contact->notes()->count());
    }

    public function test_create_form_suggests_past_events(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['event' => 'Expo SMX']);
        Contact::factory()->for(User::factory())->create(['event' => 'Someone else\'s event']);

        $this->actingAs($user)->get(route('contacts.create'))
            ->assertInertia(fn ($page) => $page->where('events', ['Expo SMX']));
    }

    public function test_contacts_list_pages_by_chosen_size(): void
    {
        $user = User::factory()->create();
        Contact::factory(30)->for($user)->create();

        $this->actingAs($user)->get(route('contacts.index', ['per_page' => 25, 'page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 5)
                ->where('contacts.total', 30)
                ->where('contacts.current_page', 2)
                ->where('filters.per_page', 25));
    }

    public function test_unsupported_page_size_falls_back_to_default(): void
    {
        $user = User::factory()->create();
        Contact::factory(12)->for($user)->create();

        $this->actingAs($user)->get(route('contacts.index', ['per_page' => 5000]))
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 10)
                ->where('filters.per_page', 10));
    }

    public function test_contacts_can_be_filtered_by_follow_up_status(): void
    {
        $this->travelTo('2026-10-31');
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['full_name' => 'Overdue', 'next_follow_up_at' => '2026-10-27']);
        Contact::factory()->for($user)->create(['full_name' => 'This week', 'next_follow_up_at' => '2026-11-03']);
        Contact::factory()->for($user)->create(['full_name' => 'Later', 'next_follow_up_at' => '2026-11-20']);
        Contact::factory()->for($user)->create(['full_name' => 'No reminder', 'next_follow_up_at' => null]);

        foreach (['overdue' => 'Overdue', 'week' => 'This week', 'none' => 'No reminder'] as $due => $name) {
            $this->actingAs($user)->get(route('contacts.index', ['due' => $due]))
                ->assertInertia(fn ($page) => $page
                    ->has('contacts.data', 1)
                    ->where('contacts.data.0.full_name', $name)
                    ->where('filters.due', $due));
        }

        // An unknown value shows everyone.
        $this->actingAs($user)->get(route('contacts.index', ['due' => 'drop table']))
            ->assertInertia(fn ($page) => $page->has('contacts.data', 4)->where('filters.due', null));
    }

    public function test_contacts_can_be_filtered_by_card(): void
    {
        $user = User::factory()->create();
        $work = Card::factory()->for($user)->create();
        $side = Card::factory()->for($user)->create();
        Contact::factory()->for($user)->create(['card_id' => $work->id, 'full_name' => 'From work card']);
        Contact::factory()->for($user)->create(['card_id' => $side->id, 'full_name' => 'From side card']);
        Contact::factory()->for($user)->create(['card_id' => null, 'full_name' => 'Added by hand']);

        $this->actingAs($user)->get(route('contacts.index', ['card' => $work->id]))
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.full_name', 'From work card')
                ->where('filters.card', $work->id));

        $this->actingAs($user)->get(route('contacts.index', ['card' => 'none']))
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.full_name', 'Added by hand'));
    }

    public function test_filtering_by_someone_elses_card_shows_all_own_contacts(): void
    {
        $user = User::factory()->create();
        Contact::factory(2)->for($user)->create();
        $otherCard = Card::factory()->create();

        $this->actingAs($user)->get(route('contacts.index', ['card' => $otherCard->id]))
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 2)
                ->where('filters.card', null));
    }
}
