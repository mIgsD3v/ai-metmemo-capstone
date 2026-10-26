<?php

namespace App\Repositories\Eloquent;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Repositories\Contracts\AssistantConversationRepositoryInterface;
use Illuminate\Support\Collection;

class AssistantConversationRepository implements AssistantConversationRepositoryInterface
{
    public function forUser(User $user, int $limit = 50): Collection
    {
        return $user->assistantConversations()
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'updated_at']);
    }

    public function findForUser(User $user, string $id): ?AssistantConversation
    {
        return $user->assistantConversations()->whereKey($id)->first();
    }

    public function create(User $user, string $title): AssistantConversation
    {
        return $user->assistantConversations()->create(['title' => $title]);
    }

    public function recentMessages(AssistantConversation $conversation, int $limit): Collection
    {
        return $conversation->messages()
            ->reorder()
            ->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function addMessage(AssistantConversation $conversation, string $role, string $content, ?array $meta = null): AssistantMessage
    {
        return $conversation->messages()->create(['role' => $role, 'content' => $content, 'meta' => $meta]);
    }

    public function delete(AssistantConversation $conversation): void
    {
        $conversation->delete();
    }
}
