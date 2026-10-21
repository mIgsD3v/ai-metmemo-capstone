<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_card(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cards.store'), [
            'full_name' => 'Maria Santos',
            'company' => 'Santos Realty',
            'is_public' => true,
        ])->assertRedirect(route('cards.index'));

        $card = $user->cards()->first();
        $this->assertTrue(Str::isUuid($card->id));
        $this->assertSame('Santos Realty', $card->company);
    }

    public function test_public_link_uses_the_card_uuid_not_the_name(): void
    {
        $card = Card::factory()->create(['full_name' => 'Maria Santos']);

        $url = route('public-card.show', $card);

        $this->assertStringEndsWith('/c/'.$card->id, $url);
        $this->assertStringNotContainsString('maria', strtolower($url));
    }

    public function test_ids_are_uuids_across_tables(): void
    {
        $card = Card::factory()->create();
        $contact = Contact::factory()->for($card->user)->create(['card_id' => $card->id]);
        $note = $contact->notes()->create(['body' => 'Hello']);

        foreach ([$card->user->id, $card->id, $contact->id, $note->id] as $id) {
            $this->assertTrue(Str::isUuid($id), "{$id} is not a UUID");
        }
    }

    public function test_owner_can_update_card(): void
    {
        $card = Card::factory()->create();

        $this->actingAs($card->user)->put(route('cards.update', $card), [
            'full_name' => $card->full_name,
            'job_title' => 'IT Infrastructure Engineer',
        ])->assertRedirect(route('cards.index'));

        $this->assertSame('IT Infrastructure Engineer', $card->fresh()->job_title);
    }

    public function test_owner_can_create_card_with_its_own_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cards.store'), [
            'full_name' => 'Maria Santos',
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertRedirect(route('cards.index'));

        $card = $user->cards()->first();
        $this->assertStringStartsWith('card-photos/', $card->photo_path);
        Storage::disk('public')->assertExists($card->photo_path);
    }

    public function test_each_card_keeps_its_own_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        foreach (['Work', 'Side project'] as $name) {
            $this->actingAs($user)->post(route('cards.store'), [
                'full_name' => $name,
                'photo' => UploadedFile::fake()->image("{$name}.jpg"),
            ]);
        }

        [$first, $second] = $user->cards()->pluck('photo_path');
        $this->assertNotSame($first, $second);
    }

    public function test_new_photo_replaces_and_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $card = Card::factory()->create(['photo_path' => UploadedFile::fake()->image('old.jpg')->store('card-photos', 'public')]);
        $oldPath = $card->photo_path;

        $this->actingAs($card->user)->put(route('cards.update', $card), [
            'full_name' => $card->full_name,
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ])->assertRedirect(route('cards.index'));

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($card->fresh()->photo_path);
    }

    public function test_editing_without_a_photo_keeps_the_current_one(): void
    {
        Storage::fake('public');
        $card = Card::factory()->create(['photo_path' => UploadedFile::fake()->image('me.jpg')->store('card-photos', 'public')]);

        $this->actingAs($card->user)->put(route('cards.update', $card), [
            'full_name' => 'New Name',
        ]);

        $this->assertSame($card->photo_path, $card->fresh()->photo_path);
    }

    public function test_owner_can_remove_card_photo(): void
    {
        Storage::fake('public');
        $card = Card::factory()->create(['photo_path' => UploadedFile::fake()->image('me.jpg')->store('card-photos', 'public')]);

        $this->actingAs($card->user)->put(route('cards.update', $card), [
            'full_name' => $card->full_name,
            'remove_photo' => true,
        ]);

        $this->assertNull($card->fresh()->photo_path);
        Storage::disk('public')->assertMissing($card->photo_path);
    }

    public function test_card_photo_must_be_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cards.store'), [
            'full_name' => 'Maria Santos',
            'photo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_deleting_a_card_deletes_its_photo(): void
    {
        Storage::fake('public');
        $card = Card::factory()->create(['photo_path' => UploadedFile::fake()->image('me.jpg')->store('card-photos', 'public')]);

        $this->actingAs($card->user)->delete(route('cards.destroy', $card));

        Storage::disk('public')->assertMissing($card->photo_path);
    }

    public function test_owner_can_open_print_page(): void
    {
        $card = Card::factory()->create(['full_name' => 'Albert Miguela']);

        $this->actingAs($card->user)->get(route('cards.print', $card))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cards/Print')
                ->where('card.full_name', 'Albert Miguela')
                ->where('card.public_url', route('public-card.show', $card)));
    }

    public function test_owner_can_download_vcard_even_when_card_is_private(): void
    {
        $card = Card::factory()->private()->create(['full_name' => 'Albert Miguela']);

        $response = $this->actingAs($card->user)->get(route('cards.vcard', $card))->assertOk();

        $this->assertStringContainsString('FN:Albert Miguela', $response->getContent());
    }

    public function test_others_cannot_print_or_download_someone_elses_card(): void
    {
        $card = Card::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('cards.print', $card))->assertForbidden();
        $this->actingAs($other)->get(route('cards.vcard', $card))->assertForbidden();
    }

    public function test_card_without_contacts_can_be_deleted(): void
    {
        $card = Card::factory()->create();

        $this->actingAs($card->user)->delete(route('cards.destroy', $card))->assertRedirect(route('cards.index'));

        $this->assertModelMissing($card);
    }

    public function test_card_with_contacts_cannot_be_deleted(): void
    {
        $card = Card::factory()->create();
        Contact::factory()->for($card->user)->create(['card_id' => $card->id]);

        $this->actingAs($card->user)
            ->from(route('cards.index'))
            ->delete(route('cards.destroy', $card))
            ->assertRedirect(route('cards.index'))
            ->assertSessionHas('flash.bannerStyle', 'danger');

        $this->assertModelExists($card);
    }

    public function test_owner_can_hide_and_show_a_card(): void
    {
        $card = Card::factory()->create(['is_public' => true]);

        $this->actingAs($card->user)->patch(route('cards.visibility', $card), ['is_public' => false])->assertRedirect();
        $this->assertFalse($card->fresh()->is_public);
        $this->get(route('public-card.show', $card))->assertNotFound();

        $this->actingAs($card->user)->patch(route('cards.visibility', $card), ['is_public' => true]);
        $this->assertTrue($card->fresh()->is_public);
    }

    public function test_others_cannot_hide_someone_elses_card(): void
    {
        $card = Card::factory()->create(['is_public' => true]);

        $this->actingAs(User::factory()->create())
            ->patch(route('cards.visibility', $card), ['is_public' => false])
            ->assertForbidden();

        $this->assertTrue($card->fresh()->is_public);
    }

    public function test_user_cannot_edit_someone_elses_card(): void
    {
        $card = Card::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('cards.update', $card), ['full_name' => 'Hacked'])
            ->assertForbidden();
    }
}
