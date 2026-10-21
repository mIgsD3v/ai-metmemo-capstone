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
    public function paginateForUser(User $user, ?string $search = null, ?string $card = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->filtered($user, $search, $card)
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
    private function filtered(User $user, ?string $search, ?string $card)
    {
        return $user->contacts()
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

    private function whereMatches($query, string $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('full_name', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('event', 'like', "%{$search}%");
        });
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
