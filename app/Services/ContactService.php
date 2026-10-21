<?php

namespace App\Services;

use App\Enums\ContactSource;
use App\Models\Card;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use App\Repositories\Contracts\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContactService
{
    /** New leads get a follow-up reminder this many days after the meeting. */
    public const DEFAULT_FOLLOW_UP_DAYS = 2;

    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
    ) {}

    /**
     * @param  string|null  $card  A card id, "none" for contacts not captured by a card, or null for all.
     */
    public function paginate(User $user, ?string $search = null, ?string $card = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->contacts->paginateForUser($user, $search, $card, $perPage);
    }

    /** @return Collection<int, Contact> */
    public function recent(User $user, int $limit = 5): Collection
    {
        return $this->contacts->recentForUser($user, $limit);
    }

    public function countThisWeek(User $user): int
    {
        return $this->contacts->countCreatedSince($user, now()->startOfWeek());
    }

    /**
     * Save a contact read from a scanned paper business card, after the owner reviewed the fields.
     *
     * @param  array  $data  Validated contact fields, with an optional "note".
     */
    public function createFromScan(User $user, array $data): Contact
    {
        return DB::transaction(function () use ($user, $data) {
            $contact = $this->contacts->create($user, [
                'met_at' => today(),
                'next_follow_up_at' => today()->addDays(self::DEFAULT_FOLLOW_UP_DAYS),
                ...array_filter(Arr::except($data, ['note']), fn ($value) => $value !== null),
                'source' => ContactSource::Scan,
            ]);

            if (filled($data['note'] ?? null)) {
                $this->contacts->addNote($contact, $data['note']);
            }

            return $contact;
        });
    }

    /**
     * @param  array  $data  Validated contact fields, with an optional first "note".
     */
    public function create(User $user, array $data): Contact
    {
        return DB::transaction(function () use ($user, $data) {
            $contact = $this->contacts->create($user, [
                'source' => ContactSource::Manual,
                ...Arr::except($data, ['note']),
            ]);

            if (filled($data['note'] ?? null)) {
                $this->contacts->addNote($contact, $data['note']);
            }

            return $contact;
        });
    }

    /** Notes are added on the contact page, never through an edit. */
    public function update(Contact $contact, array $data): Contact
    {
        return $this->contacts->update($contact, Arr::except($data, ['note']));
    }

    /** @return array<int, string> Event names the user has used before, newest first. */
    public function recentEvents(User $user, int $limit = 20): array
    {
        return $this->contacts->recentEvents($user, $limit);
    }

    public function delete(Contact $contact): void
    {
        $this->contacts->delete($contact);
    }

    public function addNote(Contact $contact, string $body): ContactNote
    {
        return $this->contacts->addNote($contact, $body);
    }

    /** @return Collection<int, Contact> */
    public function search(User $user, string $search, int $limit = 10): Collection
    {
    /**
     * @param  int  $withinDays  0 = due today or overdue; 7 = also those due in the coming week.
     * @return Collection<int, Contact>
     */
    public function dueForFollowUp(User $user, int $withinDays = 0): Collection
    {
        return $this->contacts->dueForFollowUp($user, now()->addDays($withinDays));
    }

        return $this->contacts->searchForUser($user, $search, $limit);
    }

    /** @return Collection<int, ContactNote> Newest first. */
    public function notes(Contact $contact, int $limit = 50): Collection
    {
        return $this->contacts->notesFor($contact, $limit);
    }

    /**
     * A visitor left their details on a public card: save them as a lead for the card owner.
     *
     * @param  array  $data  Validated lead form fields, with an optional "message".
     */
    public function captureLead(Card $card, array $data): Contact
    {
        return DB::transaction(function () use ($card, $data) {
            $contact = $this->contacts->create($card->user, [
                ...Arr::only($data, ['full_name', 'email', 'phone', 'company']),
                'card_id' => $card->id,
                'source' => ContactSource::LeadForm,
                'met_at' => today(),
                'next_follow_up_at' => today()->addDays(self::DEFAULT_FOLLOW_UP_DAYS),
            ]);

            if (filled($data['message'] ?? null)) {
                $this->contacts->addNote($contact, 'Message from card visitor: '.$data['message']);
            }

            return $contact;
        });
    }
}
