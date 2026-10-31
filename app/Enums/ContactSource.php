<?php

namespace App\Enums;

enum ContactSource: string
{
    case Manual = 'manual';
    case LeadForm = 'lead_form';
    case Scan = 'scan';
    case Swap = 'swap';

    /** Same wording as the Contacts list. */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Added by hand',
            self::LeadForm => 'Card form',
            self::Scan => 'Scanned',
            self::Swap => 'Card swap',
        };
    }
}
