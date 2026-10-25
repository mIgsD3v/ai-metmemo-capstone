<?php

namespace App\Observers;

use App\Models\Contact;
use App\Services\Ai\NotesIndexer;

use function Illuminate\Support\defer;

class ContactObserver
{
    public function __construct(
        private readonly NotesIndexer $indexer,
    ) {}

    /** The database removes a deleted contact's notes; remove them from the assistant's index too. */
    public function deleted(Contact $contact): void
    {
        if ($this->indexer->enabled()) {
            defer(fn () => $this->indexer->forgetContact($contact));
        }
    }
}
