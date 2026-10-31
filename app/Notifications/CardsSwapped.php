<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\Contact;

/** Another MetMemo user swapped cards with the owner from one of the owner's public cards. */
class CardsSwapped extends MetMemoNotification
{
    public function __construct(
        private readonly Contact $contact,
        private readonly Card $card,
    ) {}

    protected function payload(): array
    {
        $from = $this->contact->company ? " from {$this->contact->company}" : '';
        $card = '"'.($this->card->company ?: $this->card->job_title ?: $this->card->full_name).'"';

        return [
            'kind' => 'swap',
            'title' => 'New card swap',
            'body' => "{$this->contact->full_name}{$from} swapped cards with you from your {$card} card.",
            'url' => route('contacts.show', $this->contact),
        ];
    }
}
