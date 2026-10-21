<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use App\Repositories\Contracts\ContactRepositoryInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

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
     * Everything stays under storage/, never the system temp folder: a web server started without a
     * TEMP variable reports C:\Windows there, which isn't writable.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function spreadsheet(User $user, string $format, ?string $search, ?string $card): string
    {
        $folder = storage_path('app/exports');
        File::ensureDirectoryExists($folder);
        $path = $folder.DIRECTORY_SEPARATOR.Str::uuid().'.'.$format;

        // The CSV gets a byte-order mark, so Excel reads accents (Peñafrancia) correctly.
        $writer = $format === 'xlsx'
            ? new XlsxWriter(new XlsxOptions(tempFolder: $folder))
            : new CsvWriter(new CsvOptions(SHOULD_ADD_BOM: true));
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValuesWithStyle(array_keys($this->row(new Contact)), new Style(fontBold: true)));

            foreach ($this->contacts->exportForUser($user, $search, $card) as $contact) {
                $row = array_values($this->row($contact));
                // Excel files store text as text; only CSV cells can be read back as formulas.
                $writer->addRow(Row::fromValues($format === 'csv' ? array_map($this->neutralize(...), $row) : $row));
            }
        } finally {
            $writer->close();
        }

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
