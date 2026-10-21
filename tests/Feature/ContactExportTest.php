<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

class ContactExportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, string>> */
    private function rows(TestResponse $response, string $type): array
    {
        $path = $response->baseResponse->getFile()->getPathname();

        return SimpleExcelReader::create($path, $type)->getRows()->all();
    }

    public function test_csv_export_has_every_column_and_your_notes(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['company' => 'Herma Corporation']);
        $contact = Contact::factory()->for($user)->create([
            'full_name' => 'Peñafrancia Santos', 'card_id' => $card->id, 'phone' => '+63 917 123 4567',
            'event' => 'Real Estate Expo SMX', 'tags' => ['investor', 'condo'], 'next_follow_up_at' => '2026-10-30',
        ]);
        $this->travelTo('2026-10-17 10:00');
        $contact->notes()->create(['body' => 'Asked for a quote for 3 branches.']);

        $response = $this->actingAs($user)->get(route('contacts.export', 'csv'))->assertOk();

        $this->assertStringContainsString('metmemo-contacts-', $response->headers->get('Content-Disposition'));
        [$row] = $this->rows($response, 'csv');
        $this->assertSame('Peñafrancia Santos', $row['Name']);
        $this->assertSame('+63 917 123 4567', $row['Phone']); // a real phone number is left as is
        $this->assertSame('Herma Corporation', $row['Card']);
        $this->assertSame('Real Estate Expo SMX', $row['Where you met']);
        $this->assertSame('2026-10-30', $row['Next follow-up']);
        $this->assertSame('investor, condo', $row['Tags']);
        $this->assertSame('2026-10-17: Asked for a quote for 3 branches.', $row['Notes']);
    }

    public function test_csv_export_neutralizes_formulas_typed_into_the_lead_form(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['full_name' => '=HYPERLINK("http://evil.example","Click")', 'company' => '@SUM(1+1)']);

        [$row] = $this->rows($this->actingAs($user)->get(route('contacts.export', 'csv')), 'csv');

        $this->assertSame('\'=HYPERLINK("http://evil.example","Click")', $row['Name']);
        $this->assertSame("'@SUM(1+1)", $row['Company']);
    }

    public function test_excel_export_is_a_real_spreadsheet(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['full_name' => 'Maria Santos']);

        $response = $this->actingAs($user)->get(route('contacts.export', 'xlsx'))->assertOk();

        $this->assertStringEndsWith('.xlsx', $response->baseResponse->getFile()->getFilename());
        $this->assertSame('Maria Santos', $this->rows($response, 'xlsx')[0]['Name']);
    }

    public function test_export_only_ever_contains_your_own_contacts(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['full_name' => 'Mine']);
        Contact::factory()->create(['full_name' => 'Someone else\'s']);

        $rows = $this->rows($this->actingAs($user)->get(route('contacts.export', 'csv')), 'csv');

        $this->assertSame(['Mine'], array_column($rows, 'Name'));
    }

    public function test_export_follows_the_lists_search_and_card_filter(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        Contact::factory()->for($user)->create(['full_name' => 'Juan From Card', 'card_id' => $card->id]);
        Contact::factory()->for($user)->create(['full_name' => 'Juan Scanned', 'card_id' => null]);
        Contact::factory()->for($user)->create(['full_name' => 'Maria From Card', 'card_id' => $card->id]);

        $search = $this->rows($this->actingAs($user)->get(route('contacts.export', ['format' => 'csv', 'search' => 'Juan'])), 'csv');
        $byCard = $this->rows($this->actingAs($user)->get(route('contacts.export', ['format' => 'csv', 'search' => 'Juan', 'card' => $card->id])), 'csv');
        $none = $this->rows($this->actingAs($user)->get(route('contacts.export', ['format' => 'csv', 'card' => 'none'])), 'csv');

        $this->assertEqualsCanonicalizing(['Juan From Card', 'Juan Scanned'], array_column($search, 'Name'));
        $this->assertSame(['Juan From Card'], array_column($byCard, 'Name'));
        $this->assertSame(['Juan Scanned'], array_column($none, 'Name'));
    }

    public function test_someone_elses_or_malformed_card_filter_is_ignored(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create(['full_name' => 'Mine']);
        $foreignCard = Card::factory()->create();

        foreach ([$foreignCard->id, 'not-a-uuid'] as $card) {
            $rows = $this->rows($this->actingAs($user)->get(route('contacts.export', ['format' => 'csv', 'card' => $card]))->assertOk(), 'csv');
            $this->assertSame(['Mine'], array_column($rows, 'Name'));
        }
    }

    public function test_empty_export_still_has_the_header_row(): void
    {
        $user = User::factory()->create();

        $path = $this->actingAs($user)->get(route('contacts.export', 'csv'))->baseResponse->getFile()->getPathname();

        $this->assertStringStartsWith('Name,Email,Phone', ltrim(file_get_contents($path), "\u{FEFF}"));
    }

    public function test_vcard_export_has_one_card_per_contact_with_context(): void
    {
        $user = User::factory()->create();
        $juan = Contact::factory()->for($user)->create(['full_name' => 'Juan Dela Cruz', 'event' => 'PhilConstruct 2026', 'met_at' => '2026-10-15', 'tags' => ['investor']]);
        $juan->notes()->create(['body' => 'Wants a 2BR in BGC.']);
        Contact::factory()->for($user)->create(['full_name' => 'Maria Santos']);

        $response = $this->actingAs($user)->get(route('contacts.export', 'vcf'))->assertOk();
        $vcf = str_replace("\r\n ", '', $response->streamedContent());

        $this->assertStringContainsString('text/vcard', $response->headers->get('Content-Type'));
        $this->assertSame(2, substr_count($vcf, 'BEGIN:VCARD'));
        $this->assertStringContainsString('FN:Juan Dela Cruz', $vcf);
        $this->assertStringContainsString('Met at PhilConstruct 2026 on Oct 15\, 2026.', $vcf);
        $this->assertStringContainsString('Wants a 2BR in BGC.', $vcf);
        $this->assertStringContainsString('CATEGORIES:investor', $vcf);
    }

    public function test_unknown_format_and_guests_are_refused(): void
    {
        $this->get('/contacts/export/pdf')->assertNotFound();
        $this->get(route('contacts.export', 'csv'))->assertRedirect(route('login'));
    }
}
