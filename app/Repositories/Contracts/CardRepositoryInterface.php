<?php

namespace App\Repositories\Contracts;

use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Collection;

interface CardRepositoryInterface
{
    /** @return Collection<int, Card> */
    public function forUser(User $user): Collection;

    public function findPublic(string $id): ?Card;

    public function create(User $user, array $attributes): Card;

    public function update(Card $card, array $attributes): Card;

    public function delete(Card $card): void;
}
