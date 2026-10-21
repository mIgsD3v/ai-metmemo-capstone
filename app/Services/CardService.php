<?php

namespace App\Services;

use App\Exceptions\CardHasContactsException;
use App\Models\Card;
use App\Models\User;
use App\Repositories\Contracts\CardRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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

    /**
     * @param  array  $data  Validated card fields, with an optional uploaded "photo".
     */
    public function create(User $user, array $data): Card
    {
        $photo = $data['photo'] ?? null;

        return $this->cards->create($user, [
            ...Arr::except($data, ['photo', 'remove_photo']),
            'photo_path' => $photo ? $this->storePhoto($photo) : null,
        ]);
    }

    /**
     * A new "photo" replaces the old one; "remove_photo" clears it. The old file is deleted either way.
     *
     * @param  array  $data  Validated card fields, with optional "photo" and "remove_photo".
     */
    public function update(Card $card, array $data): Card
    {
        $attributes = Arr::except($data, ['photo', 'remove_photo']);
        $oldPath = $card->photo_path;

        if (isset($data['photo'])) {
            $attributes['photo_path'] = $this->storePhoto($data['photo']);
        } elseif ($data['remove_photo'] ?? false) {
            $attributes['photo_path'] = null;
        }

        $card = $this->cards->update($card, $attributes);

        if ($oldPath && $oldPath !== $card->photo_path) {
            Storage::disk(Card::PHOTO_DISK)->delete($oldPath);
        }

        return $card;
    }

    private function storePhoto(UploadedFile $photo): string
    {
        return $photo->store('card-photos', Card::PHOTO_DISK);
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

        if ($card->photo_path) {
            Storage::disk(Card::PHOTO_DISK)->delete($card->photo_path);
        }
    }

    public function setVisibility(Card $card, bool $isPublic): Card
    {
        return $this->cards->update($card, ['is_public' => $isPublic]);
    }
}
