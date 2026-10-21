<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use App\Repositories\Contracts\ContactRepositoryInterface;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Exports the owner's contacts, with the same search and card filter as the Contacts list:
 * a spreadsheet (CSV or Excel) for working with the data, or vCards to import into a phone.
 */
class ContactExportService
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly VCardService $vcards,
    ) {}

    /**
     * Writes the spreadsheet to a temporary file (row by row, so memory stays flat) and returns its path.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function spreadsheet(User $user, string $format, ?string $search, ?string $card): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'metmemo-export-'.Str::uuid().'.'.$format;
        $writer = SimpleExcelWriter::create($path, $format);

        foreach ($this->contacts->exportForUser($user, $search, $card) as $contact) {
            $row = $this->row($contact);
            // Excel files store text as text; only CSV cells can be read back as formulas.
            $writer->addRow($format === 'csv' ? array_map($this->neutralize(...), $row) : $row);
        }

        if (! $writer->getNumberOfRows()) {
            $writer->addHeader(array_keys($this->row(new Contact)));
        }

        $writer->close();

        return $path;
    }

    /** Every matching contact as one .vcf file, which phones and Google Contacts import in one go. */
    public function vcards(User $user, ?string $search, ?string $card): iterable
    {
        foreach ($this->contacts->exportForUser($user, $search, $card) as $contact) {
            yield $this->vcards->buildContact($contact);
        }
    }

    public function filename(string $format): string
    {
        return 'metmemo-contacts-'.today()->toDateString().'.'.$format;
    }

    /** @return array<string, string> Column name => value, in the order they appear in the file. */
    private function row(Contact $contact): array
    {
        return [
            'Name' => $contact->full_name ?? '',
            'Email' => $contact->email ?? '',
            'Phone' => $contact->phone ?? '',
            'Company' => $contact->company ?? '',
            'Job title' => $contact->job_title ?? '',
            'Card' => $contact->card?->company ?: ($contact->card?->full_name ?? ''),
            'Source' => $contact->source?->label() ?? '',
            'Where you met' => $contact->event ?? '',
            'Met on' => $contact->met_at?->toDateString() ?? '',
            'Next follow-up' => $contact->next_follow_up_at?->toDateString() ?? '',
            'Last followed up' => $contact->last_contacted_at?->toDateString() ?? '',
            'Tags' => implode(', ', $contact->tags ?? []),
            // Newest first, one per line, each with its date.
            'Notes' => $contact->exists
                ? $contact->notes->map(fn ($note) => $note->created_at->format('Y-m-d').': '.$note->body)->implode("\n")
                : '',
            'Added on' => $contact->created_at?->toDateString() ?? '',
        ];
    }

    /**
     * CSV injection guard. Lead-form fields are typed by strangers: a "name" like =HYPERLINK(...)
     * would run as a formula when the CSV is opened in Excel. Such cells get a leading apostrophe,
     * which spreadsheets read as "this is text". Plain phone numbers like "+63 917 123 4567" are left alone.
     */
    private function neutralize(string $value): string
    {
        if ($value === '' || ! in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $value;
        }

        return preg_match('/^\+?[\d\s().-]+$/', $value) ? $value : "'".$value;
    }
}
