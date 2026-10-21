<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Contact;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds vCard 3.0 files: a card owner for visitors to save, and the owner's contacts for export
 * (to import into Google Contacts or a phone).
 */
class VCardService
{
    /** Longest side of the embedded photo, in pixels. Contact photos are shown small. */
    private const PHOTO_SIZE = 400;

    public function build(Card $card): string
    {
        $lines = $this->person($card->full_name, $card->company, $card->job_title, $card->email, $card->phone);

        if ($card->website) {
            $lines[] = 'URL:'.$this->escape($card->website);
        }
        if ($card->location) {
            $lines[] = 'ADR;TYPE=WORK:;;'.$this->escape($card->location).';;;;';
        }

        $lines[] = 'URL;TYPE=MetMemo:'.route('public-card.show', $card);

        if ($photo = $this->photo($card)) {
            $lines[] = "PHOTO;ENCODING=b;TYPE={$photo['type']}:".base64_encode($photo['data']);
        }

        return $this->finish($lines);
    }

    /** One of the owner's contacts, with where you met and your notes, so the context travels with them. */
    public function buildContact(Contact $contact): string
    {
        $lines = $this->person($contact->full_name, $contact->company, $contact->job_title, $contact->email, $contact->phone);

        $met = array_filter([
            $contact->event ? "Met at {$contact->event}" : null,
            $contact->met_at ? 'on '.$contact->met_at->format('M j, Y') : null,
        ]);
        $notes = $contact->notes->map(fn ($note) => $note->created_at->format('M j, Y').': '.$note->body);
        $summary = collect([$met ? implode(' ', $met).'.' : null, ...$notes])->filter()->implode("\n");

        if ($summary !== '') {
            $lines[] = 'NOTE:'.$this->escape($summary);
        }
        if ($contact->tags) {
            $lines[] = 'CATEGORIES:'.implode(',', array_map($this->escape(...), $contact->tags));
        }

        return $this->finish($lines);
    }

    public function contactsFilename(): string
    {
        return 'metmemo-contacts-'.today()->toDateString().'.vcf';
    }

    /** @return array<int, string> The lines every vCard starts with. */
    private function person(string $name, ?string $company, ?string $title, ?string $email, ?string $phone): array
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.$this->escape($name),
            'N:'.$this->escape($name).';;;;',
        ];

        if ($company) {
            $lines[] = 'ORG:'.$this->escape($company);
        }
        if ($title) {
            $lines[] = 'TITLE:'.$this->escape($title);
        }
        if ($email) {
            $lines[] = 'EMAIL;TYPE=INTERNET:'.$this->escape($email);
        }
        if ($phone) {
            $lines[] = 'TEL;TYPE=CELL:'.$this->escape($phone);
        }

        return $lines;
    }

    /** @param  array<int, string>  $lines */
    private function finish(array $lines): string
    {
        $lines[] = 'END:VCARD';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * The card's photo, embedded: phones (iPhone especially) ignore photo links in a vCard.
     * Shrunk to a small JPEG so the file stays light; the original is used if it can't be decoded.
     *
     * @return array{type: string, data: string}|null
     */
    private function photo(Card $card): ?array
    {
        $file = $card->displayPhotoFile();
        $data = $file ? Storage::disk($file['disk'])->get($file['path']) : null;

        if (! $data) {
            return null;
        }

        $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($data) : false;

        if (! $image) {
            $type = str_ends_with(strtolower($file['path']), '.png') ? 'PNG' : 'JPEG';

            return ['type' => $type, 'data' => $data];
        }

        $scale = min(1, self::PHOTO_SIZE / max(imagesx($image), imagesy($image)));
        $resized = imagescale($image, (int) round(imagesx($image) * $scale), (int) round(imagesy($image) * $scale));

        ob_start();
        imagejpeg($resized ?: $image, null, 85);

        return ['type' => 'JPEG', 'data' => ob_get_clean()];
    }

    /**
     * vCard lines longer than 75 bytes continue on the next line, starting with a space.
     * mb_strcut never splits a multi-byte character (e.g. "ñ") across lines.
     */
    private function fold(string $line): string
    {
        $parts = [];

        for ($offset = 0, $length = strlen($line); $offset < $length; $offset += strlen($part)) {
            $parts[] = $part = mb_strcut($line, $offset, $offset === 0 ? 75 : 74, 'UTF-8');
        }

        return implode("\r\n ", $parts);
    }

    public function filename(Card $card): string
    {
        return (Str::slug($card->full_name) ?: 'contact').'.vcf';
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ',', ';', "\r\n", "\n"], ['\\\\', '\,', '\;', '\n', '\n'], $value);
    }
}
