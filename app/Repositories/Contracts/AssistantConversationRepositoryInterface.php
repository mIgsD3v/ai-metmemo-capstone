<?php

namespace App\Repositories\Contracts;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Support\Collection;

interface AssistantConversationRepositoryInterface
{
    /** @return Collection<int, AssistantConversation> Most recently used first. */
    public function forUser(User $user, int $limit = 50): Collection;

    public function findForUser(User $user, string $id): ?AssistantConversation;

    public function create(User $user, string $title): AssistantConversation;

    /** @return Collection<int, AssistantMessage> The last $limit messages, oldest first. */
    public function recentMessages(AssistantConversation $conversation, int $limit): Collection;

    public function addMessage(AssistantConversation $conversation, string $role, string $content, ?array $meta = null): AssistantMessage;

    public function delete(AssistantConversation $conversation): void;
}
