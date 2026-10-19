<?php

namespace App\Repositories\Contracts;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ContactRepositoryInterface
{
    /**
     * @param  string|null  $card  A card id, "none" for contacts not captured by a card, or null for all.
     */
    public function paginateForUser(User $user, ?string $search = null, ?string $card = null, int $perPage = 10): LengthAwarePaginator;

    /** @return Collection<int, Contact> */
    public function recentForUser(User $user, int $limit): Collection;

    public function countCreatedSince(User $user, CarbonInterface $since): int;

    /** @return array<int, string> */
    public function recentEvents(User $user, int $limit): array;

    public function create(User $user, array $attributes): Contact;

    public function update(Contact $contact, array $attributes): Contact;

    public function delete(Contact $contact): void;

    public function addNote(Contact $contact, string $body): ContactNote;

    /**
     * Contacts whose follow-up date is on or before the given day.
     * Also backs the assistant's get_overdue_followups tool later.
     *
     * @return Collection<int, Contact>
     */
    public function dueForFollowUp(User $user, CarbonInterface $asOf): Collection;
}
