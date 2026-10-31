<?php

namespace App\Repositories\Eloquent;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use App\Repositories\Contracts\ContactRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class ContactRepository implements ContactRepositoryInterface
{
    public function paginateForUser(User $user, ?string $search = null, ?string $card = null, int $perPage = 10, ?string $due = null): LengthAwarePaginator
    {
        return $this->filtered($user, $search, $card, $due)
            ->with('card:id,full_name,job_title')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function exportForUser(User $user, ?string $search = null, ?string $card = null): LazyCollection
    {
        // Read in chunks, so a big export never loads every contact (and note) into memory at once.
        return $this->filtered($user, $search, $card)
            ->with(['card:id,full_name,company,job_title', 'notes'])
            ->lazy(200);
    }

    /** The Contacts list's filters, shared by the page and its exports so both show the same people. */
    private function filtered(User $user, ?string $search, ?string $card, ?string $due = null)
    {
        return $user->contacts()
            // Follow-up status: overdue (before today), due within the next 7 days, or no reminder set.
            ->when($due === 'overdue', fn ($query) => $query->whereDate('next_follow_up_at', '<', today()))
            ->when($due === 'week', fn ($query) => $query->whereDate('next_follow_up_at', '>=', today())->whereDate('next_follow_up_at', '<=', today()->addDays(7)))
            ->when($due === 'none', fn ($query) => $query->whereNull('next_follow_up_at'))
            ->when($card === 'none', fn ($query) => $query->whereNull('card_id'))
            ->when($card !== null && $card !== 'none', fn ($query) => $query->where('card_id', $card))
            ->when($search, fn ($query, string $search) => $this->whereMatches($query, $search))
            ->latest()
            ->orderBy('id'); // Stable order for chunked reads when timestamps tie.
    }

    public function searchForUser(User $user, string $search, int $limit): Collection
    {
        return $this->whereMatches($user->contacts(), $search)
            ->latest()
            ->take($limit)
            ->get();
    }

    public function notesFor(Contact $contact, int $limit): Collection
    {
        return $contact->notes()->take($limit)->get();
    }

    /**
     * Every word must appear in the name, company, email or event, in any order and any case, so
     * "Raffy Mendoza" finds Rafael "Raffy" Mendoza (failure F8: the whole phrase used to be one LIKE).
     */
    private function whereMatches($query, string $search)
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $query->where(function ($query) use ($word) {
                $query->whereLike('full_name', "%{$word}%")
                    ->orWhereLike('company', "%{$word}%")
                    ->orWhereLike('email', "%{$word}%")
                    ->orWhereLike('event', "%{$word}%");
            });
        }

        return $query;
    }

    public function recentForUser(User $user, int $limit): Collection
    {
        return $user->contacts()
            ->with('card:id,full_name,job_title')
            ->latest()
            ->take($limit)
            ->get();
    }

    public function countCreatedSince(User $user, CarbonInterface $since): int
    {
        return $user->contacts()->where('created_at', '>=', $since)->count();
    }

    public function countCreatedBetween(User $user, CarbonInterface $from, CarbonInterface $until): int
    {
        return $user->contacts()->where('created_at', '>=', $from)->where('created_at', '<', $until)->count();
    }

    public function recentEvents(User $user, int $limit): array
    {
        return $user->contacts()
            ->whereNotNull('event')
            ->where('event', '!=', '')
            ->selectRaw('event, max(created_at) as last_used')
            ->groupBy('event')
            ->orderByDesc('last_used')
            ->limit($limit)
            ->pluck('event')
            ->all();
    }

    public function create(User $user, array $attributes): Contact
    {
        return $user->contacts()->create($attributes);
    }

    public function update(Contact $contact, array $attributes): Contact
    {
        $contact->update($attributes);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }

    public function addNote(Contact $contact, string $body): ContactNote
    {
        return $contact->notes()->create(['body' => $body]);
    }

    public function dueForFollowUp(User $user, CarbonInterface $asOf): Collection
    {
        return $user->contacts()
            ->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', '<=', $asOf)
            ->orderBy('next_follow_up_at')
            ->get();
    }
}
