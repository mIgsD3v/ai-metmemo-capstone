<?php

namespace App\Http\Controllers;

use App\Exceptions\CardHasContactsException;
use App\Http\Requests\CardRequest;
use App\Models\Card;
use App\Services\CardService;
use App\Services\VCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CardController extends Controller
{
    public function __construct(
        private readonly CardService $cards,
        private readonly VCardService $vcards,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Cards/Index', [
            'cards' => $this->cards->listForUser($request->user())->map(fn (Card $card) => [
                ...$card->only(['id', 'full_name', 'job_title', 'company', 'is_public', 'contacts_count', 'leads_count']),
                'photo_url' => $card->displayPhotoUrl(),
                'public_url' => route('public-card.show', $card),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Cards/Form', [
            'card' => null,
            'accountPhotoUrl' => $this->accountPhotoUrl($request),
        ]);
    }

    public function store(CardRequest $request): RedirectResponse
    {
        $this->cards->create($request->user(), $request->validated());

        return to_route('cards.index')->with('flash.banner', 'Card created.');
    }

    public function edit(Request $request, Card $card): Response
    {
        Gate::authorize('update', $card);

        return Inertia::render('Cards/Form', [
            'card' => $card,
            'accountPhotoUrl' => $this->accountPhotoUrl($request),
        ]);
    }

    /** Shown as the fallback preview when the card has no photo of its own. */
    private function accountPhotoUrl(Request $request): ?string
    {
        $user = $request->user();

        return $user->profile_photo_path ? $user->profile_photo_url : null;
    }

    public function update(CardRequest $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        $this->cards->update($card, $request->validated());

        return to_route('cards.index')->with('flash.banner', 'Card updated.');
    }

    /** Print-ready business card (front and back) for the owner. */
    public function print(Card $card): Response
    {
        Gate::authorize('update', $card);

        return Inertia::render('Cards/Print', [
            'card' => [
                ...$card->only(['id', 'full_name', 'job_title', 'company', 'email', 'phone', 'website', 'location', 'is_public']),
                'public_url' => route('public-card.show', $card),
            ],
        ]);
    }

    /** The owner's own vCard, even while the card is private. */
    public function vcard(Card $card): HttpResponse
    {
        Gate::authorize('update', $card);

        return response($this->vcards->build($card), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->vcards->filename($card).'"',
        ]);
    }

    public function destroy(Card $card): RedirectResponse
    {
        Gate::authorize('delete', $card);

        try {
            $this->cards->delete($card);
        } catch (CardHasContactsException $e) {
            return back()
                ->with('flash.banner', "This card can't be deleted because {$e->contactCount} contacts came from it. Hide it instead.")
                ->with('flash.bannerStyle', 'danger');
        }

        return to_route('cards.index')->with('flash.banner', 'Card deleted.');
    }

    public function visibility(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        $isPublic = $request->validate(['is_public' => ['required', 'boolean']])['is_public'];
        $this->cards->setVisibility($card, $isPublic);

        return back()->with('flash.banner', $isPublic
            ? 'Card is public again. Its link and QR code work.'
            : 'Card hidden. Its link and QR code are turned off.');
    }
}
