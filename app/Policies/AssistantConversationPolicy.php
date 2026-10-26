<?php

namespace App\Policies;

use App\Models\AssistantConversation;
use App\Models\User;

class AssistantConversationPolicy
{
    public function view(User $user, AssistantConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function delete(User $user, AssistantConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }
}
