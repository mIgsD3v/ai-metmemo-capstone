<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScanContactRequest;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use App\Services\CardService;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** Scan a paper business card: the AI reads it, the owner reviews the fields, then it is saved as a contact. */
class ScanController extends Controller
{
    public function __construct(
        private readonly AiServiceClient $ai,
        private readonly CardService $cards,
        private readonly ContactService $contacts,
    ) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Scan/Create', [
            'cards' => $this->cards->listForUser($request->user())
                ->map(fn ($card) => $card->only(['id', 'full_name', 'job_title']))
                ->all(),
        ]);
    }

    /** Called by the scan page with the photo; returns the fields for review. Nothing is saved here. */
    public function read(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            return response()->json(['fields' => $this->ai->scanCard($request->file('image'))]);
        } catch (AiServiceException $e) {
            // No card contents in the log: only that a read failed and why.
            Log::warning('Card scan failed', ['status' => $e->getCode(), 'reason' => $e->getMessage()]);

            return response()->json([
                'message' => 'The AI could not read this card right now. You can type the details below.',
            ], 503);
        }
    }

    public function store(ScanContactRequest $request): RedirectResponse
    {
        $contact = $this->contacts->createFromScan($request->user(), $request->validated());

        return to_route('contacts.show', $contact)->with('flash.banner', 'Contact saved from scan.');
    }
}
