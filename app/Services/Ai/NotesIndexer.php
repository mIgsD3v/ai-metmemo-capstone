<?php

namespace App\Services\Ai;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the assistant's notes index (RAG, in the AI service) in step with the database.
 *
 * Indexing never blocks or breaks saving a note: if the AI service is down, a warning is logged
 * and `php artisan metmemo:index-notes` catches up later.
 */
class NotesIndexer
{
    /** Notes per request to the AI service (it embeds each batch in one call). */
    public const BATCH = 100;

    public function __construct(
        private readonly AiServiceClient $ai,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.ai.index_notes');
    }

    /**
     * @param  iterable<int, ContactNote>  $notes  With their contact loaded (or loadable).
     * @return int How many were indexed.
     */
    public function index(iterable $notes): int
    {
        $indexed = 0;

        foreach (collect($notes)->chunk(self::BATCH) as $batch) {
            try {
                $this->ai->indexNotes($batch->map($this->payload(...))->values()->all());
                $indexed += $batch->count();
            } catch (AiServiceException $e) {
                // Counts only: note text is personal data and stays out of the logs.
                Log::warning('Notes index: could not index notes', ['count' => $batch->count(), 'reason' => $e->getMessage()]);
            }
        }

        return $indexed;
    }

    public function forgetContact(Contact $contact): void
    {
        $this->forget($contact->user_id, $contact->id);
    }

    public function forgetUser(User $user): void
    {
        $this->forget($user->id);
    }

    /** @return array{note_id: string, user_id: string, contact_id: string, contact_name: string, body: string, created_at: string} */
    public function payload(ContactNote $note): array
    {
        return [
            'note_id' => $note->id,
            'user_id' => $note->contact->user_id,
            'contact_id' => $note->contact_id,
            'contact_name' => $note->contact->full_name,
            'body' => $note->body,
            'created_at' => $note->created_at->toIso8601String(),
        ];
    }

    private function forget(string $userId, ?string $contactId = null): void
    {
        try {
            $this->ai->forgetNotes($userId, $contactId);
        } catch (AiServiceException $e) {
            Log::warning('Notes index: could not remove notes', ['contact' => $contactId !== null, 'reason' => $e->getMessage()]);
        }
    }
}
