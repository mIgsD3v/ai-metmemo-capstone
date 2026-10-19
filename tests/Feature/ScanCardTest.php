<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScanCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('scan.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Scan/Create'));
    }

    public function test_read_returns_fields_from_ai_service(): void
    {
        Http::fake(['*/scan' => Http::response([
            'full_name' => ['value' => 'Juan Dela Cruz', 'confidence' => 0.97, 'needs_review' => false],
            'email' => ['value' => 'juan@example.ph', 'confidence' => 0.6, 'needs_review' => true],
            'phone' => ['value' => null, 'confidence' => 0, 'needs_review' => false],
            'company' => ['value' => 'Cruz Trading', 'confidence' => 0.9, 'needs_review' => false],
            'job_title' => ['value' => null, 'confidence' => 0, 'needs_review' => false],
        ])]);

        $this->actingAs(User::factory()->create())
            ->post(route('scan.read'), ['image' => UploadedFile::fake()->image('card.jpg')])
            ->assertOk()
            ->assertJsonPath('fields.full_name.value', 'Juan Dela Cruz')
            ->assertJsonPath('fields.email.needs_review', true);
    }

    public function test_read_degrades_gracefully_when_ai_is_down(): void
    {
        Http::fake(['*/scan' => Http::response(['detail' => 'Card scanner is not configured'], 503)]);

        $this->actingAs(User::factory()->create())
            ->post(route('scan.read'), ['image' => UploadedFile::fake()->image('card.jpg')])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI could not read this card right now. You can type the details below.');
    }

    public function test_read_rejects_non_images(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('scan.read'), ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_reviewed_scan_is_saved_as_contact_with_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('scan.store'), [
            'full_name' => 'Juan Dela Cruz',
            'company' => 'Cruz Trading',
            'event' => 'Expo SMX',
            'note' => 'Wants a quote for 3 branches.',
        ])->assertRedirect();

        $contact = $user->contacts()->first();
        $this->assertSame('scan', $contact->source->value);
        $this->assertSame('Expo SMX', $contact->event);
        $this->assertNotNull($contact->met_at);
        $this->assertNotNull($contact->next_follow_up_at);
        $this->assertSame('Wants a quote for 3 branches.', $contact->notes()->first()->body);
    }
}
