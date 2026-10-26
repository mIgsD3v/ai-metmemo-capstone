<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Ai\AiServiceException;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The follow-up assistant page and its saved chats. The AI service does the thinking; this controller
 * decides whose data it may read: always the signed-in user, never an id from the browser.
 */
class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantService $assistant,
    ) {}

    /** A new chat, or (with a conversation) an earlier one to read or continue. */
    public function show(Request $request, ?AssistantConversation $conversation = null): Response
    {
        if ($conversation) {
            Gate::authorize('view', $conversation);
        }

        return Inertia::render('Assistant', [
            'conversations' => $this->assistant->conversations($request->user())
                ->map(fn (AssistantConversation $c) => ['id' => $c->id, 'title' => $c->title, 'updated_at' => $c->updated_at->toIso8601String()])
                ->all(),
            'conversation' => $conversation ? [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages->map($this->message(...))->all(),
            ] : null,
        ]);
    }

    /** One chat turn. Starts a conversation when there isn't one yet. */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();
        $conversation = null;
        if (isset($validated['conversation_id'])) {
            // Someone else's chat looks the same as one that doesn't exist.
            $conversation = $this->assistant->find($user, $validated['conversation_id']) ?? abort(404);
        }

        try {
            [$conversation, $answer] = $this->assistant->ask($user, $conversation, $validated['message']);
        } catch (AiServiceException $e) {
            // Status and reason only: the question itself is personal and stays out of the log.
            Log::warning('Assistant failed', ['status' => $e->getCode(), 'reason' => $e->getMessage()]);

            return response()->json([
                'message' => 'The assistant is unavailable right now. Your contacts and notes are fine; try again in a minute.',
            ], 503);
        }

        return response()->json([
            'conversation' => ['id' => $conversation->id, 'title' => $conversation->title],
            'message' => $this->message($answer),
        ]);
    }

    public function destroy(AssistantConversation $conversation): RedirectResponse
    {
        Gate::authorize('delete', $conversation);

        $this->assistant->delete($conversation);

        return to_route('assistant.show')->with('flash.banner', 'Chat deleted.');
    }

    private function message(AssistantMessage $message): array
    {
        $meta = $message->meta ?? [];

        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'tools' => $meta['tool_calls'] ?? [],
            'guardrails' => $meta['guardrails'] ?? [],
            'drafts' => collect($meta['drafts'] ?? [])->map(fn (array $draft) => $draft + ['url' => $this->contactUrl($draft['contact_id'] ?? null)])->all(),
            'sources' => collect($meta['sources'] ?? [])->map(fn (array $source) => $source + ['url' => $this->contactUrl($source['contact_id'] ?? null)])->all(),
        ];
    }

    /** A link to the contact (the contact page's own policy still checks ownership when it's opened). */
    private function contactUrl(?string $contactId): ?string
    {
        return $contactId && Str::isUuid($contactId) ? route('contacts.show', $contactId) : null;
    }
}
