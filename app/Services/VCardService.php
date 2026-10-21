<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a vCard 3.0 file so visitors can save the card owner to their phone contacts.
 */
class VCardService
{
    /** Longest side of the embedded photo, in pixels. Contact photos are shown small. */
    private const PHOTO_SIZE = 400;

    public function build(Card $card): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.$this->escape($card->full_name),
            'N:'.$this->escape($card->full_name).';;;;',
        ];

        if ($card->company) {
            $lines[] = 'ORG:'.$this->escape($card->company);
        }
        if ($card->job_title) {
            $lines[] = 'TITLE:'.$this->escape($card->job_title);
        }
        if ($card->email) {
            $lines[] = 'EMAIL;TYPE=INTERNET:'.$this->escape($card->email);
        }
        if ($card->phone) {
            $lines[] = 'TEL;TYPE=CELL:'.$this->escape($card->phone);
        }
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
