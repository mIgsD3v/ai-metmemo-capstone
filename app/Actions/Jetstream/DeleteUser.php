<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use App\Services\Ai\NotesIndexer;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    public function __construct(
        private readonly NotesIndexer $notesIndex,
    ) {}

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        // Their contacts and notes go with the account (database cascade); their search index too.
        if ($this->notesIndex->enabled()) {
            $this->notesIndex->forgetUser($user);
        }

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
