<?php

namespace App\Observers;

use App\Models\ContactNote;
use App\Services\Ai\NotesIndexer;

use function Illuminate\Support\defer;

class ContactNoteObserver
{
    public function __construct(
        private readonly NotesIndexer $indexer,
    ) {}

    /** Make a new note searchable by the assistant, after the response is sent so saving stays fast. */
    public function created(ContactNote $note): void
    {
        if ($this->indexer->enabled()) {
            defer(fn () => $this->indexer->index([$note]));
        }
    }
}
