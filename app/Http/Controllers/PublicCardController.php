<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadCaptureRequest;
use App\Http\Requests\SwapCardsRequest;
use App\Models\Card;
use App\Services\CardService;
use App\Services\ContactService;
use App\Services\VCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public card page that visitors open from a link or QR code. No login needed; a signed-in MetMemo user can
 * also swap cards from it.
 */
class PublicCardController extends Controller
{
    public function __construct(
        private readonly CardService $cards,
        private readonly ContactService $contacts,
        private readonly VCardService $vcards,
    ) {}

    public function show(Request $request, string $card): InertiaResponse
    {
        $card = $this->findOrFail($card);

        return Inertia::render('Public/Card', [
            // Only public-facing fields; never the owner's account details.
            'card' => [
                ...$card->only(['id', 'full_name', 'job_title', 'company', 'bio', 'email', 'phone', 'website', 'location']),
                // The card's own photo, else the owner's uploaded account photo; null falls back to initials.
                'photo_url' => $card->displayPhotoUrl(),
            ],
            'publicUrl' => route('public-card.show', $card),
            'viewer' => $this->viewer($request, $card),
        ]);
    }

    public function vcard(string $card): Response
    {
        $card = $this->findOrFail($card);

        return response($this->vcards->build($card), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->vcards->filename($card).'"',
        ]);
    }

    public function storeLead(LeadCaptureRequest $request, string $card): RedirectResponse
    {
        $card = $this->findOrFail($card);

        $this->contacts->captureLead($card, $request->validated());

        return back()->with('flash.banner', 'Thanks! '.$card->full_name.' will get back to you.');
    }

    /** Guests are sent to log in, then come back here to the card. */
    public function startSwap(string $card): RedirectResponse
    {
        return redirect()->route('public-card.show', $this->findOrFail($card));
    }

    public function swap(SwapCardsRequest $request, string $card): RedirectResponse
    {
        $card = $this->findOrFail($card);
        $visitor = $request->user();

        if ($card->user_id === $visitor->getKey()) {
            return back()->with('flash.banner', "That's your own card.")->with('flash.bannerStyle', 'danger');
        }

        $mine = $request->validated('card_id') ? $visitor->cards()->find($request->validated('card_id')) : null;
        $contact = $this->contacts->swapCards($card, $visitor, $mine);

        return redirect()->route('contacts.show', $contact)
            ->with('flash.banner', "You and {$card->full_name} swapped cards. You're in each other's contacts now.");
    }

    /**
     * What a signed-in visitor needs to swap cards: their own cards to choose from, and whether they already
     * swapped with this card's owner. Null for guests.
     */
    private function viewer(Request $request, Card $card): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($card->user_id === $user->getKey()) {
            return ['isOwner' => true];
        }

        return [
            'isOwner' => false,
            'name' => $user->name,
            'cards' => $this->cards->listForUser($user)
                ->map(fn (Card $mine) => $mine->only(['id', 'full_name', 'job_title', 'company']))
                ->values(),
            'swappedContactId' => $this->contacts->swappedWith($user, $card->user)?->id,
        ];
    }

    private function findOrFail(string $id): Card
    {
        return $this->cards->findPublic($id) ?? abort(404);
    }
}
