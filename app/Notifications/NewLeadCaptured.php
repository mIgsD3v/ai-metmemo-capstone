<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\Contact;

/** A visitor left their details on one of the owner's public cards. Real time: a fast reply wins the lead. */
class NewLeadCaptured extends MetMemoNotification
{
    public function __construct(
        private readonly Contact $contact,
        private readonly Card $card,
    ) {}

    protected function payload(): array
    {
        $from = $this->contact->company ? " from {$this->contact->company}" : '';

        return [
            'kind' => 'lead',
            'title' => 'New lead from your card',
            'body' => "{$this->contact->full_name}{$from} left their details on your {$this->cardLabel()} card.",
            'url' => route('contacts.show', $this->contact),
        ];
    }

    /** Which card, in the owner's words: the company, else the job title, else the name on it. */
    private function cardLabel(): string
    {
        return '"'.($this->card->company ?: $this->card->job_title ?: $this->card->full_name).'"';
    }
}
