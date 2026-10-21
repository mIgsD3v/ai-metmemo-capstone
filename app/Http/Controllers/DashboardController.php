<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Contact;
use App\Services\Ai\AiServiceClient;
use App\Services\CardService;
use App\Services\ContactService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly CardService $cards,
        private readonly AiServiceClient $ai,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $due = $this->contacts->dueForFollowUp($user);
        $cards = $this->cards->listForUser($user);

        return Inertia::render('Dashboard', [
            'stats' => [
                'cards' => $cards->count(),
                'contacts' => $user->contacts()->count(),
                'due' => $due->count(),
                'thisWeek' => $this->contacts->countThisWeek($user),
                'lastWeekSoFar' => $this->contacts->countLastWeekSoFar($user),
            ],
            'dueContacts' => $due->take(5)->map->only(['id', 'full_name', 'company', 'next_follow_up_at'])->values(),
            'recentContacts' => $this->contacts->recent($user)->map(fn (Contact $contact) => [
                ...$contact->only(['id', 'full_name', 'company', 'source']),
                'card' => $contact->card?->full_name,
                'created_at' => $contact->created_at->toDateString(),
            ]),
            // Public cards for the quick QR share, newest first. Hidden ones are left out: their QR doesn't work.
            'shareCards' => $cards->where('is_public', true)->map(fn (Card $card) => [
                ...$card->only(['id', 'full_name', 'job_title', 'company']),
                'photo_url' => $card->displayPhotoUrl(),
                'public_url' => route('public-card.show', $card),
            ])->values(),
            // Lazy so a slow or offline AI service never blocks the first page load.
            'aiOnline' => Inertia::defer(fn () => $this->ai->isHealthy()),
        ]);
    }
}
