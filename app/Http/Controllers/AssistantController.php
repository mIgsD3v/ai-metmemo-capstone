<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The follow-up assistant page. The AI service does the thinking; this controller decides whose data
 * it may read: always the signed-in user, never an id from the browser.
 */
class AssistantController extends Controller
{
    public function __construct(
        private readonly AiServiceClient $ai,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Assistant');
    }

    /** One chat turn. The page keeps the conversation and sends the earlier turns as memory. */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        try {
            $reply = $this->ai->chat($user->id, $user->name, $validated['message'], $validated['history'] ?? []);
        } catch (AiServiceException $e) {
            // Status and reason only: the question itself is personal and stays out of the log.
            Log::warning('Assistant failed', ['status' => $e->getCode(), 'reason' => $e->getMessage()]);

            return response()->json([
                'message' => 'The assistant is unavailable right now. Your contacts and notes are fine; try again in a minute.',
            ], 503);
        }

        return response()->json([
            'answer' => $reply['answer'] ?? '',
            'tool_calls' => $reply['tool_calls'] ?? [],
            'drafts' => collect($reply['drafts'] ?? [])->map(fn (array $draft) => $draft + ['url' => $this->contactUrl($draft['contact_id'] ?? null)])->all(),
            'sources' => collect($reply['sources'] ?? [])->map(fn (array $source) => $source + ['url' => $this->contactUrl($source['contact_id'] ?? null)])->all(),
        ]);
    }

    /** A link to the contact (the contact page's own policy still checks ownership when it's opened). */
    private function contactUrl(?string $contactId): ?string
    {
        return $contactId && Str::isUuid($contactId) ? route('contacts.show', $contactId) : null;
    }
}
