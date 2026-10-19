<?php

namespace App\Exceptions;

use RuntimeException;

/** A card that already captured contacts cannot be deleted; hide it instead. */
class CardHasContactsException extends RuntimeException
{
    public function __construct(public readonly int $contactCount)
    {
        parent::__construct("This card has {$contactCount} contacts and cannot be deleted.");
    }
}
