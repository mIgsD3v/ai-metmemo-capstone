<?php

namespace App\Services;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Repositories\Contracts\AssistantConversationRepositoryInterface;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Saved chats with the follow-up assistant. */
class AssistantService
{
    /** Earlier messages sent to the AI as memory (the AI service accepts up to 20). */
    public const MEMORY = 20;

    public function __construct(
        private readonly AssistantConversationRepositoryInterface $conversations,
        private readonly AiServiceClient $ai,
    ) {}

    /** @return Collection<int, AssistantConversation> */
    public function conversations(User $user): Collection
    {
        return $this->conversations->forUser($user);
    }

    public function find(User $user, string $id): ?AssistantConversation
    {
        return $this->conversations->findForUser($user, $id);
    }

    /**
     * One turn: ask with this conversation's own saved messages as memory, then save the question and the answer.
     * The memory comes from the database, never from the browser, so nobody can feed the AI a fake earlier turn.
     * If the AI fails, nothing is saved (no half-finished chats) and the exception reaches the caller.
     *
     * @return array{0: AssistantConversation, 1: AssistantMessage}
     *
     * @throws AiServiceException
     */
    public function ask(User $user, ?AssistantConversation $conversation, string $message): array
    {
        $memory = $conversation
            ? $this->conversations->recentMessages($conversation, self::MEMORY)
                ->map(fn (AssistantMessage $turn) => ['role' => $turn->role, 'content' => Str::limit($turn->content, 1990)])
                ->all()
            : [];

        $reply = $this->ai->chat($user->id, $user->name, $message, $memory);

        return DB::transaction(function () use ($user, $conversation, $message, $reply) {
            $conversation ??= $this->conversations->create($user, Str::limit(Str::squish($message), 80));

            $this->conversations->addMessage($conversation, 'user', $message);
            $answer = $this->conversations->addMessage($conversation, 'assistant', $reply['answer'] ?? '', [
                'tool_calls' => $reply['tool_calls'] ?? [],
                'drafts' => $reply['drafts'] ?? [],
                'sources' => $reply['sources'] ?? [],
            ]);
            $conversation->touch(); // most recently used goes to the top of the list

            return [$conversation, $answer];
        });
    }

    public function delete(AssistantConversation $conversation): void
    {
        $this->conversations->delete($conversation);
    }
}
