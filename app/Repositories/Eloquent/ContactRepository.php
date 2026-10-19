<?php

namespace App\Repositories\Eloquent;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use App\Repositories\Contracts\ContactRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ContactRepository implements ContactRepositoryInterface
{
    public function paginateForUser(User $user, ?string $search = null, ?string $card = null, int $perPage = 10): LengthAwarePaginator
    {
        return $user->contacts()
            ->with('card:id,full_name,job_title')
            ->when($card === 'none', fn ($query) => $query->whereNull('card_id'))
            ->when($card !== null && $card !== 'none', fn ($query) => $query->where('card_id', $card))
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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
