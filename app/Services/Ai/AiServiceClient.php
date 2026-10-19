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
