<?php

namespace App\Services;

use App\Enums\ContactSource;
use App\Models\Card;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use App\Notifications\FollowUpsDue;
use App\Notifications\NewLeadCaptured;
use App\Repositories\Contracts\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

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
    public function paginate(User $user, ?string $search = null, ?string $card = null, int $perPage = 10, ?string $due = null): LengthAwarePaginator
    {
        return $this->contacts->paginateForUser($user, $search, $card, $perPage, $due);
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

    /** Last week up to this same moment (Monday to "now minus a week"), so the comparison is fair mid-week. */
    public function countLastWeekSoFar(User $user): int
    {
        return $this->contacts->countCreatedBetween($user, now()->subWeek()->startOfWeek(), now()->subWeek());
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

    /**
     * You reached out today: record it and set the next follow-up (or none).
     * This is what takes a contact off the "due" list and feeds "average days to follow up".
     */
    public function markFollowedUp(Contact $contact, ?int $nextInDays): Contact
    {
        return $this->contacts->update($contact, [
            'last_contacted_at' => now(),
            'first_followed_up_at' => $contact->first_followed_up_at ?? now(),
            'next_follow_up_at' => $nextInDays ? today()->addDays($nextInDays) : null,
        ]);
    }

    /**
     * @param  int  $withinDays  0 = due today or overdue; 7 = also those due in the coming week.
     * @return Collection<int, Contact>
     */
    public function dueForFollowUp(User $user, int $withinDays = 0): Collection
    {
        return $this->contacts->dueForFollowUp($user, now()->addDays($withinDays));
    }

    /** @return Collection<int, Contact> */
    public function search(User $user, string $search, int $limit = 10): Collection
    {
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
        $contact = DB::transaction(function () use ($card, $data) {
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

        // The lead is already saved: a notification problem (e.g. Pusher down) must never fail the visitor's form.
        try {
            $card->user->notify(new NewLeadCaptured($contact, $card));
        } catch (Throwable $e) {
            report($e);
        }

        return $contact;
    }

    /**
     * Morning summary of who's due today or overdue. Skipped when nobody is due,
     * or when today's summary was already sent (so a re-run never doubles it).
     */
    public function sendFollowUpReminder(User $user): bool
    {
        $due = $this->dueForFollowUp($user);

        if ($due->isEmpty()) {
            return false;
        }

        $alreadySent = $user->notifications()
            ->where('type', FollowUpsDue::class)
            ->where('created_at', '>=', today())
            ->exists();

        if ($alreadySent) {
            return false;
        }

        $overdue = $due->filter(fn (Contact $contact) => $contact->next_follow_up_at->lt(today()))->count();
        $user->notify(new FollowUpsDue($due->count(), $overdue, $due->take(3)->pluck('full_name')->all()));

        return true;
    }
}
