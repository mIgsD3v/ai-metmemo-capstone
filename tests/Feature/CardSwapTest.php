<?php

namespace Tests\Feature;

use App\Enums\ContactSource;
use App\Models\Card;
use App\Models\User;
use App\Notifications\CardsSwapped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardSwapTest extends TestCase
{
    use RefreshDatabase;

    public function test_swapping_cards_adds_each_person_to_the_others_contacts(): void
    {
        Notification::fake();
        $this->travelTo('2026-10-31');
        $maria = Card::factory()->create(['full_name' => 'Maria Santos', 'company' => 'Santos Realty', 'email' => 'maria@example.com']);
        $ben = User::factory()->create();
        $bensCard = Card::factory()->for($ben)->create(['full_name' => 'Ben Cruz', 'company' => 'Cruz Logistics', 'phone' => '+63 917 000 0000']);

        $response = $this->actingAs($ben)->post(route('public-card.swap', $maria), ['card_id' => $bensCard->id]);

        $forBen = $ben->contacts()->sole();
        $response->assertRedirect(route('contacts.show', $forBen));
        $this->assertSame('Maria Santos', $forBen->full_name);
        $this->assertSame('maria@example.com', $forBen->email);
        $this->assertSame(ContactSource::Swap, $forBen->source);
        $this->assertSame($maria->user_id, $forBen->swapped_user_id);
        $this->assertSame($bensCard->id, $forBen->card_id);
        $this->assertSame('2026-11-02', $forBen->next_follow_up_at->toDateString());

        $forMaria = $maria->user->contacts()->sole();
        $this->assertSame('Ben Cruz', $forMaria->full_name);
        $this->assertSame('Cruz Logistics', $forMaria->company);
        $this->assertSame('+63 917 000 0000', $forMaria->phone);
        $this->assertSame($ben->id, $forMaria->swapped_user_id);
        $this->assertSame($maria->id, $forMaria->card_id);

        Notification::assertSentTo($maria->user, CardsSwapped::class);
    }

    public function test_swapping_again_updates_the_contacts_instead_of_duplicating_them(): void
    {
        Notification::fake();
        $maria = Card::factory()->create();
        $ben = User::factory()->create();
        $bensCard = Card::factory()->for($ben)->create(['job_title' => 'Analyst']);

        $this->actingAs($ben)->post(route('public-card.swap', $maria), ['card_id' => $bensCard->id]);
        $bensCard->update(['job_title' => 'Manager']);
        $this->actingAs($ben)->post(route('public-card.swap', $maria), ['card_id' => $bensCard->id]);

        $this->assertSame(1, $ben->contacts()->count());
        $this->assertSame(1, $maria->user->contacts()->count());
        $this->assertSame('Manager', $maria->user->contacts()->sole()->job_title);
        Notification::assertSentToTimes($maria->user, CardsSwapped::class, 1);
    }

    public function test_swapping_copies_each_cards_photo_into_the_new_contact(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('card-photos/maria.jpg', 'maria-photo');
        Storage::disk('public')->put('card-photos/ben.jpg', 'ben-photo');
        $maria = Card::factory()->create(['photo_path' => 'card-photos/maria.jpg']);
        $ben = User::factory()->create();
        $bensCard = Card::factory()->for($ben)->create(['photo_path' => 'card-photos/ben.jpg']);

        $this->actingAs($ben)->post(route('public-card.swap', $maria), ['card_id' => $bensCard->id]);

        $mariaForBen = $ben->contacts()->sole();
        $benForMaria = $maria->user->contacts()->sole();
        $this->assertSame('maria-photo', Storage::disk('public')->get($mariaForBen->photo_path));
        $this->assertSame('ben-photo', Storage::disk('public')->get($benForMaria->photo_path));
        $this->assertStringStartsWith('contact-photos/', $mariaForBen->photo_path);
        $this->assertNotNull($mariaForBen->photo_url);

        // A copy: Maria changing her card photo doesn't touch Ben's contact until they swap again...
        $old = $mariaForBen->photo_path;
        Storage::disk('public')->put('card-photos/maria-new.jpg', 'maria-new-photo');
        $maria->update(['photo_path' => 'card-photos/maria-new.jpg']);
        $this->assertSame('maria-photo', Storage::disk('public')->get($old));

        // ...which swaps the photo for the new one and removes the old copy.
        $this->actingAs($ben)->post(route('public-card.swap', $maria), ['card_id' => $bensCard->id]);
        $mariaForBen->refresh();
        $this->assertSame('maria-new-photo', Storage::disk('public')->get($mariaForBen->photo_path));
        Storage::disk('public')->assertMissing($old);

        // Deleting the contact deletes its photo.
        $mariaForBen->delete();
        Storage::disk('public')->assertMissing($mariaForBen->photo_path);
    }

    public function test_without_a_card_the_visitor_sends_their_account_name_and_email(): void
    {
        $maria = Card::factory()->create();
        $ben = User::factory()->create(['name' => 'Ben Cruz', 'email' => 'ben@example.com']);

        $this->actingAs($ben)->post(route('public-card.swap', $maria))->assertRedirect();

        $forMaria = $maria->user->contacts()->sole();
        $this->assertSame('Ben Cruz', $forMaria->full_name);
        $this->assertSame('ben@example.com', $forMaria->email);
        $this->assertNull($ben->contacts()->sole()->card_id);
    }

    public function test_you_cannot_send_someone_elses_card(): void
    {
        $maria = Card::factory()->create();
        $ben = User::factory()->create();
        $strangersCard = Card::factory()->create();

        $this->actingAs($ben)->post(route('public-card.swap', $maria), ['card_id' => $strangersCard->id])
            ->assertSessionHasErrors('card_id');

        $this->assertSame(0, $ben->contacts()->count());
    }

    public function test_you_cannot_swap_with_your_own_card(): void
    {
        $card = Card::factory()->create();

        $this->actingAs($card->user)->post(route('public-card.swap', $card))->assertRedirect();

        $this->assertSame(0, $card->user->contacts()->count());
    }

    public function test_guests_are_sent_to_log_in_and_then_back_to_the_card(): void
    {
        $card = Card::factory()->create();

        $this->get(route('public-card.swap.start', $card))->assertRedirect(route('login'));
        $this->post(route('public-card.swap', $card))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('public-card.swap.start', $card))
            ->assertRedirect(route('public-card.show', $card));
    }

    public function test_card_page_tells_a_signed_in_visitor_what_they_can_swap(): void
    {
        $maria = Card::factory()->create();
        $ben = User::factory()->create();
        $bensCard = Card::factory()->for($ben)->create();

        $this->actingAs($ben)->get(route('public-card.show', $maria))
            ->assertInertia(fn ($page) => $page
                ->where('viewer.isOwner', false)
                ->where('viewer.cards.0.id', $bensCard->id)
                ->where('viewer.swappedContactId', null));

        $this->actingAs($maria->user)->get(route('public-card.show', $maria))
            ->assertInertia(fn ($page) => $page->where('viewer.isOwner', true));

        auth()->logout();
        $this->get(route('public-card.show', $maria))
            ->assertInertia(fn ($page) => $page->where('viewer', null));
    }
}
