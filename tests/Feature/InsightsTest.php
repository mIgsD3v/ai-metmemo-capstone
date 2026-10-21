<?php

namespace Tests\Feature;

use App\Enums\ContactSource;
use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private function contact(User $user, string $created, array $attributes = []): Contact
    {
        $this->travelTo($created);

        return Contact::factory()->for($user)->create(['next_follow_up_at' => null, 'event' => null, ...$attributes]);
    }

    public function test_weekly_counts_split_by_source_with_every_week_present(): void
    {
        $user = User::factory()->create();
        $this->contact($user, '2026-10-20 10:00', ['source' => ContactSource::Scan]);      // this week (Mon Oct 19)
        $this->contact($user, '2026-10-21 10:00', ['source' => ContactSource::LeadForm]);
        $this->contact($user, '2026-10-06 10:00', ['source' => ContactSource::Manual]);    // two weeks ago
        $this->contact($user, '2026-06-01 10:00', ['source' => ContactSource::Scan]);      // outside 4 weeks
        $this->travelTo('2026-10-21 12:00');

        $this->actingAs($user)->get(route('insights', ['weeks' => 4]))->assertInertia(fn ($page) => $page
            ->component('Insights')
            ->where('insights.range', ['weeks' => 4, 'from' => '2026-09-28', 'to' => '2026-10-21'])
            ->has('insights.weekly', 4)
            ->where('insights.weekly.1', ['week' => '2026-10-05', 'manual' => 1, 'lead_form' => 0, 'scan' => 0])
            ->where('insights.weekly.3', ['week' => '2026-10-19', 'manual' => 0, 'lead_form' => 1, 'scan' => 1])
            ->where('insights.totals.contacts', 3)
            ->where('insights.totals.fromCards', 1));
    }

    public function test_compares_with_the_previous_period_of_the_same_length(): void
    {
        $user = User::factory()->create();
        $this->contact($user, '2026-10-20 10:00');
        $this->contact($user, '2026-09-20 10:00'); // previous 4 weeks
        $this->contact($user, '2026-09-21 10:00');
        $this->travelTo('2026-10-21 12:00');

        $this->actingAs($user)->get(route('insights', ['weeks' => 4]))->assertInertia(fn ($page) => $page
            ->where('insights.totals.contacts', 1)
            ->where('insights.totals.contactsBefore', 2));
    }

    public function test_events_show_the_top_ones_and_group_the_rest(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 10) as $n) {
            foreach (range(1, $n) as $_) {
                $this->contact($user, '2026-10-20 10:00', ['event' => "Event {$n}"]);
            }
        }
        $this->travelTo('2026-10-21 12:00');

        $this->actingAs($user)->get(route('insights'))->assertInertia(fn ($page) => $page
            ->has('insights.events.top', 8)
            ->where('insights.events.top.0', ['label' => 'Event 10', 'total' => 10])
            ->where('insights.events.other', 3) // Event 2 (2) + Event 1 (1)
            ->where('insights.events.otherCount', 2));
    }

    public function test_cards_include_ones_that_brought_nobody_and_tell_same_named_cards_apart(): void
    {
        $user = User::factory()->create();
        $busy = Card::factory()->for($user)->create(['company' => 'Herma Corporation']);
        Card::factory()->for($user)->create(['company' => 'Herma Corporation']);
        $this->contact($user, '2026-10-20 10:00', ['card_id' => $busy->id]);
        Card::factory()->create(); // someone else's
        $this->travelTo('2026-10-21 12:00');

        $this->actingAs($user)->get(route('insights'))->assertInertia(fn ($page) => $page
            ->has('insights.cards', 2)
            ->where('insights.cards.0.total', 1)
            ->where('insights.cards.0.label', 'Herma Corporation')
            ->where('insights.cards.1.total', 0)
            ->where('insights.cards.1.label', 'Herma Corporation (2)'));
    }

    public function test_follow_up_health_and_days_to_first_follow_up(): void
    {
        $user = User::factory()->create();
        $this->travelTo('2026-10-21 12:00');
        Contact::factory()->for($user)->create(['next_follow_up_at' => '2026-10-19']); // overdue
        Contact::factory()->for($user)->create(['next_follow_up_at' => '2026-10-21']); // today
        Contact::factory()->for($user)->create(['next_follow_up_at' => '2026-10-30']); // scheduled
        Contact::factory()->for($user)->create(['met_at' => '2026-10-15', 'next_follow_up_at' => null, 'first_followed_up_at' => '2026-10-17 09:00']); // 2 days
        Contact::factory()->for($user)->create(['met_at' => '2026-10-14', 'next_follow_up_at' => null, 'first_followed_up_at' => '2026-10-18 09:00']); // 4 days

        $this->actingAs($user)->get(route('insights'))->assertInertia(fn ($page) => $page
            ->where('insights.followUps.health', ['overdue' => 1, 'today' => 1, 'upcoming' => 1, 'none' => 2])
            ->where('insights.followUps.met', 5)
            ->where('insights.followUps.followedUp', 2)
            ->where('insights.followUps.averageDays', 3));
    }

    public function test_first_follow_up_is_recorded_once(): void
    {
        $contact = Contact::factory()->create();
        $this->travelTo('2026-10-17 09:00');
        $this->actingAs($contact->user)->post(route('contacts.followed-up', $contact), ['next_in_days' => 7]);
        $this->travelTo('2026-10-24 09:00');
        $this->actingAs($contact->user)->post(route('contacts.followed-up', $contact), ['next_in_days' => 7]);

        $contact->refresh();
        $this->assertSame('2026-10-17', $contact->first_followed_up_at->toDateString());
        $this->assertSame('2026-10-24', $contact->last_contacted_at->toDateString());
    }

    public function test_only_your_own_contacts_count_and_unknown_ranges_fall_back(): void
    {
        $user = User::factory()->create();
        Contact::factory()->create(); // someone else's

        $this->actingAs($user)->get(route('insights', ['weeks' => 999]))->assertInertia(fn ($page) => $page
            ->where('insights.range.weeks', 12)
            ->where('insights.totals.contacts', 0)
            ->where('insights.followUps.health', ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'none' => 0]));
    }

    public function test_dashboard_compares_this_week_with_the_same_days_last_week(): void
    {
        $user = User::factory()->create();
        $this->contact($user, '2026-10-19 09:00'); // this Monday
        $this->contact($user, '2026-10-12 09:00'); // last Monday: counts
        $this->contact($user, '2026-10-16 09:00'); // last Friday: after "this time last week", doesn't
        $this->travelTo('2026-10-21 12:00');

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('stats.thisWeek', 1)
            ->where('stats.lastWeekSoFar', 1));
    }
}
