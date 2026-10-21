<?php

namespace App\Repositories\Eloquent;

use App\Models\Card;
use App\Models\User;
use App\Repositories\Contracts\CardRepositoryInterface;
use Illuminate\Support\Collection;

class CardRepository implements CardRepositoryInterface
{
    public function forUser(User $user): Collection
    {
        return $user->cards()
            ->chaperone() // Each card's ->user is $user, without a query per card.
            ->withCount('contacts')
            ->latest()
            ->get();
    }

    public function findPublic(string $id): ?Card
    {
        return Card::query()
            ->whereKey($id)
            ->where('is_public', true)
            ->first();
    }

    public function create(User $user, array $attributes): Card
    {
        return $user->cards()->create($attributes);
    }

    public function update(Card $card, array $attributes): Card
    {
        $card->update($attributes);

        return $card;
    }

    public function delete(Card $card): void
    {
        $card->delete();
    }
}
