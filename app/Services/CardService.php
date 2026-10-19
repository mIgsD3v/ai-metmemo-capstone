<?php

namespace App\Services;

use App\Exceptions\CardHasContactsException;
use App\Models\Card;
use App\Models\User;
use App\Repositories\Contracts\CardRepositoryInterface;
use Illuminate\Support\Collection;

class CardService
{
    public function __construct(
        private readonly CardRepositoryInterface $cards,
    ) {}

    /** @return Collection<int, Card> */
    public function listForUser(User $user): Collection
    {
        return $this->cards->forUser($user);
    }

    public function findPublic(string $id): ?Card
    {
        return $this->cards->findPublic($id);
    }

    public function create(User $user, array $data): Card
    {
        return $this->cards->create($user, $data);
    }

    public function update(Card $card, array $data): Card
    {
        return $this->cards->update($card, $data);
    }

    /**
     * A card that already brought in contacts is part of their history (and may be printed),
     * so it cannot be deleted. Hide it instead.
     *
     * @throws CardHasContactsException
     */
    public function delete(Card $card): void
    {
        $contactCount = $card->contacts()->count();

        if ($contactCount > 0) {
            throw new CardHasContactsException($contactCount);
        }

        $this->cards->delete($card);
    }

    public function setVisibility(Card $card, bool $isPublic): Card
    {
        return $this->cards->update($card, ['is_public' => $isPublic]);
    }
}
