<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Str;

/**
 * Builds a vCard 3.0 file so visitors can save the card owner to their phone contacts.
 */
class VCardService
{
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
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines)."\r\n";
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
