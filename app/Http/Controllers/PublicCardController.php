<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadCaptureRequest;
use App\Models\Card;
use App\Services\CardService;
use App\Services\ContactService;
use App\Services\VCardService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/** The public card page that visitors open from a link or QR code. No login. */
class PublicCardController extends Controller
{
    public function __construct(
        private readonly CardService $cards,
        private readonly ContactService $contacts,
        private readonly VCardService $vcards,
    ) {}

    public function show(string $card): InertiaResponse
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

    private function findOrFail(string $id): Card
    {
        return $this->cards->findPublic($id) ?? abort(404);
    }
}
