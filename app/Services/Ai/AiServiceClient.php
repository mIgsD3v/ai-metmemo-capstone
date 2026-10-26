<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the FastAPI AI service (ai-service/).
 * All AI calls (card scanner, follow-up assistant) go through here.
 */
class AiServiceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $timeout,
    ) {}

    /** True when the AI service answers its health check. */
    public function isHealthy(): bool
    {
        try {
            return $this->request()->timeout(3)->get('/health')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Read a business card photo into fields. Each field has a value, a confidence and a needs_review flag.
     *
     * @return array<string, array{value: ?string, confidence: float, needs_review: bool}>
     *
     * @throws AiServiceException when the service is down, not configured, or the read failed.
     */
    public function scanCard(UploadedFile $image): array
    {
        try {
            $response = $this->request()
                ->attach('image', $image->getContent(), 'card.'.($image->extension() ?: 'jpg'), ['Content-Type' => $image->getMimeType()])
                ->post('/scan');
        } catch (ConnectionException $e) {
            throw new AiServiceException('The AI service is not reachable.', previous: $e);
        }

        if ($response->failed()) {
            throw new AiServiceException($response->json('detail') ?: 'The card could not be read.', $response->status());
        }

        return $response->json();
    }

    /**
     * Add or update meeting notes in the assistant's search index.
     *
     * @param  array<int, array{note_id: string, user_id: string, contact_id: string, contact_name: string, body: string, created_at: string}>  $notes
     *
     * @throws AiServiceException
     */
    public function indexNotes(array $notes): void
    {
        $this->send('/notes/index', ['notes' => $notes], 'Notes could not be indexed.');
    }

    /**
     * Remove notes from the search index: one contact's, or (with no contact) all of a user's.
     *
     * @throws AiServiceException
     */
    public function forgetNotes(string $userId, ?string $contactId = null): void
    {
        $this->send('/notes/forget', array_filter(['user_id' => $userId, 'contact_id' => $contactId]), 'Notes could not be removed.');
    }

    /**
     * Ask the follow-up assistant. It reads only this user's contacts and notes, and never sends anything:
     * drafts come back for the user to review.
     *
     * @param  array<int, array{role: string, content: string}>  $history  Earlier turns, oldest first.
     * @return array{answer: string, tool_calls: list<string>, drafts: list<array>, sources: list<array>, steps: int}
     *
     * @throws AiServiceException
     */
    public function chat(string $userId, ?string $ownerName, string $message, array $history = []): array
    {
        try {
            $response = $this->request()->post('/assistant/chat', [
                'user_id' => $userId,
                'owner_name' => $ownerName,
                'message' => $message,
                'history' => $history,
            ]);
        } catch (ConnectionException $e) {
            throw new AiServiceException('The AI service is not reachable.', previous: $e);
        }

        if ($response->failed()) {
            throw new AiServiceException($response->json('detail') ?: 'The assistant could not answer.', $response->status());
        }

        return $response->json();
    }

    /** @throws AiServiceException */
    private function send(string $path, array $payload, string $failure): void
    {
        try {
            $response = $this->request()->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new AiServiceException('The AI service is not reachable.', previous: $e);
        }

        if ($response->failed()) {
            throw new AiServiceException($response->json('detail') ?: $failure, $response->status());
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($this->timeout)
            ->when($this->token, fn (PendingRequest $request, string $token) => $request->withHeaders([
                'X-Internal-Token' => $token,
            ]));
    }
}
