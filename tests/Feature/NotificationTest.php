<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use App\Notifications\FollowUpsDue;
use App\Notifications\NewLeadCaptured;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_owner_is_notified_of_a_new_lead(): void
    {
        $card = Card::factory()->create(['company' => 'Herma Corporation']);

        $this->post(route('public-card.leads.store', $card), ['full_name' => 'Maria Santos', 'company' => 'Acme', 'email' => 'maria@example.com']);

        $notification = $card->user->notifications()->sole();
        $this->assertSame(NewLeadCaptured::class, $notification->type);
        $this->assertSame('lead', $notification->data['kind']);
        $this->assertSame('Maria Santos from Acme left their details on your "Herma Corporation" card.', $notification->data['body']);
        $this->assertSame(route('contacts.show', $card->user->contacts()->sole()), $notification->data['url']);
    }

    public function test_a_failing_notification_never_loses_the_lead(): void
    {
        $card = Card::factory()->create();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Pusher is down'));

        $this->post(route('public-card.leads.store', $card), ['full_name' => 'Maria Santos', 'phone' => '+63 917 123 4567'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Maria Santos', $card->user->contacts()->sole()->full_name);
    }

    public function test_notifications_are_only_pushed_live_when_pusher_is_configured(): void
    {
        $user = User::factory()->create();
        $notification = new FollowUpsDue(1, 0, ['Juan']);

        $this->assertSame(['database'], $notification->via($user));

        config(['broadcasting.default' => 'pusher']);
        $this->assertSame(['database', 'broadcast'], $notification->via($user));
    }

    public function test_morning_reminder_summarises_due_and_overdue_follow_ups_once_a_day(): void
    {
        $this->travelTo('2026-10-21 08:00');
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['full_name' => 'Ana', 'next_follow_up_at' => '2026-10-18']);
        Contact::factory()->for($user)->create(['full_name' => 'Ben', 'next_follow_up_at' => '2026-10-21']);
        Contact::factory()->for($user)->create(['full_name' => 'Later', 'next_follow_up_at' => '2026-10-30']);
        User::factory()->create(); // nobody due: no notification

        $this->artisan('metmemo:remind-followups')->expectsOutput('Sent 1 follow-up reminder.')->assertSuccessful();
        $this->artisan('metmemo:remind-followups')->expectsOutput('Sent 0 follow-up reminders.');

        $data = $user->notifications()->sole()->data;
        $this->assertSame('2 follow-ups due today (1 overdue)', $data['title']);
        $this->assertSame('Ana, Ben.', $data['body']);
    }

    public function test_every_page_carries_only_the_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new FollowUpsDue(1, 0, ['Juan']));

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 1)
            ->missing('notifications.items')
            ->where('pusher', null));
    }

    public function test_bell_menu_lists_the_latest_50_newest_first(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 55) as $n) {
            $this->travelTo(now()->addMinute());
            $user->notify(new FollowUpsDue(1, 0, ["Person {$n}"]));
        }

        $response = $this->actingAs($user)->getJson(route('notifications.recent'))->assertOk();

        $this->assertSame(55, $response->json('unread'));
        $this->assertCount(50, $response->json('items'));
        $this->assertSame('Person 55.', $response->json('items.0.body'));
        $this->assertFalse($response->json('items.0.read'));
    }

    public function test_bell_menu_only_shows_your_own_notifications(): void
    {
        User::factory()->create()->notify(new FollowUpsDue(1, 0, ['Juan']));

        $this->actingAs(User::factory()->create())
            ->getJson(route('notifications.recent'))
            ->assertExactJson(['unread' => 0, 'items' => []]);
    }

    public function test_notifications_page_is_paginated(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 12) as $n) {
            $user->notify(new FollowUpsDue($n, 0, ["Person {$n}"]));
        }

        $this->actingAs($user)->get(route('notifications.index', ['page' => 2]))->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->where('notifications.total', 12)
            ->where('notifications.per_page', 10)
            ->where('notifications.current_page', 2)
            ->has('notifications.data', 2)
            ->where('counts', ['all' => 12, 'unread' => 12]));
    }

    public function test_notifications_page_can_show_only_unread(): void
    {
        $user = User::factory()->create();
        $user->notify(new FollowUpsDue(1, 0, ['Read one']));
        $user->notifications()->update(['read_at' => now()]);
        $user->notify(new FollowUpsDue(1, 0, ['Unread one']));

        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread', 'per_page' => 25]))->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.body', 'Unread one.')
            ->where('filters', ['filter' => 'unread', 'type' => null, 'per_page' => 25])
            ->where('counts', ['all' => 2, 'unread' => 1]));
    }

    public function test_notifications_page_can_filter_by_type(): void
    {
        $card = Card::factory()->create();
        $card->user->notify(new FollowUpsDue(1, 0, ['Juan']));
        $card->user->notify(new NewLeadCaptured(Contact::factory()->for($card->user)->create(), $card));

        $this->actingAs($card->user)->get(route('notifications.index', ['type' => 'lead']))->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.kind', 'lead')
            ->where('filters.type', 'lead'));

        // An unknown type is ignored rather than breaking the page.
        $this->actingAs($card->user)->get(route('notifications.index', ['type' => 'nope']))->assertInertia(fn ($page) => $page
            ->has('notifications.data', 2)
            ->where('filters.type', null));
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_its_page(): void
    {
        $user = User::factory()->create();
        $user->notify(new FollowUpsDue(1, 0, ['Juan']));
        $notification = $user->notifications()->sole();

        $this->actingAs($user)->post(route('notifications.open', $notification->id))->assertRedirect(route('dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_open_someone_elses_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new FollowUpsDue(1, 0, ['Juan']));

        $this->actingAs(User::factory()->create())
            ->post(route('notifications.open', $owner->notifications()->sole()->id))
            ->assertNotFound();
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new FollowUpsDue(1, 0, ['Juan']));
        $user->notify(new FollowUpsDue(2, 1, ['Ana', 'Ben']));

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_users_can_only_listen_on_their_own_channel(): void
    {
        config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher' => [
            'driver' => 'pusher', 'key' => 'key', 'secret' => 'secret', 'app_id' => '1', 'options' => ['cluster' => 'ap1'],
        ]]);
        require base_path('routes/channels.php'); // Register the channels on the Pusher broadcaster too.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$user->id])
            ->assertOk();

        $this->actingAs($user)
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.User::factory()->create()->id])
            ->assertForbidden();
    }
}
