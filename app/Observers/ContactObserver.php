<?php

namespace App\Observers;

use App\Models\Contact;
use App\Services\Ai\NotesIndexer;
use Illuminate\Support\Facades\Storage;

use function Illuminate\Support\defer;

class ContactObserver
{
    public function __construct(
        private readonly NotesIndexer $indexer,
    ) {}

    /** The database removes a deleted contact's notes; remove them from the assistant's index too, and its photo. */
    public function deleted(Contact $contact): void
    {
        if ($contact->photo_path) {
            Storage::disk(Contact::PHOTO_DISK)->delete($contact->photo_path);
        }

        if ($this->indexer->enabled()) {
            defer(fn () => $this->indexer->forgetContact($contact));
        }
    }
}
