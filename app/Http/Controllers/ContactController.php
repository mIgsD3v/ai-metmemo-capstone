<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use App\Services\CardService;
use App\Services\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /** Page sizes the list offers; anything else falls back to the first one. */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /** "Mark as followed up" choices for the next follow-up; none means no more. */
    private const NEXT_FOLLOW_UP_DAYS = [3, 7, 14, 30];

    public function __construct(
        private readonly ContactService $contacts,
        private readonly CardService $cards,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $cards = $this->cardOptions($request);
        $card = $this->cardFilter($request->query('card'), $cards);
        $perPage = $this->perPage($request->query('per_page'));

        return Inertia::render('Contacts/Index', [
            'contacts' => $this->contacts->paginate($request->user(), $search, $card, $perPage),
            'cards' => $cards,
            'filters' => ['search' => $search, 'card' => $card, 'per_page' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Contacts/Form', [
            'contact' => null,
            'cards' => $this->cardOptions($request),
            'events' => $this->contacts->recentEvents($request->user()),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = $this->contacts->create($request->user(), $request->validated());

        return to_route('contacts.show', $contact)->with('flash.banner', 'Contact saved.');
    }

    public function show(Contact $contact): Response
    {
        Gate::authorize('view', $contact);

        return Inertia::render('Contacts/Show', [
            'contact' => $contact->load(['notes', 'card:id,full_name']),
        ]);
    }

    public function edit(Request $request, Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        return Inertia::render('Contacts/Form', [
            'contact' => $contact,
            'cards' => $this->cardOptions($request),
            'events' => $this->contacts->recentEvents($request->user()),
        ]);
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $this->contacts->update($contact, $request->validated());

        return to_route('contacts.show', $contact)->with('flash.banner', 'Contact updated.');
    }

    public function followedUp(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $nextInDays = $request->validate([
            'next_in_days' => ['nullable', 'integer', Rule::in(self::NEXT_FOLLOW_UP_DAYS)],
        ])['next_in_days'] ?? null;

        $contact = $this->contacts->markFollowedUp($contact, $nextInDays);

        $next = $contact->next_follow_up_at
            ? 'Next follow-up: '.$contact->next_follow_up_at->format('M j').'.'
            : 'No more follow-ups scheduled.';

        return back()->with('flash.banner', "Marked {$contact->full_name} as followed up. {$next}");
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $this->contacts->delete($contact);

        return to_route('contacts.index')->with('flash.banner', 'Contact deleted.');
    }

    private function cardOptions(Request $request): array
    {
        return $this->cards->listForUser($request->user())
            ->map(fn ($card) => $card->only(['id', 'full_name', 'job_title']))
            ->all();
    }

    private function perPage(mixed $value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];
    }

    /** Only "none" or the id of one of the user's own cards; anything else shows all contacts. */
    private function cardFilter(mixed $value, array $cards): ?string
    {
        if ($value === 'none') {
            return 'none';
        }

        return is_string($value) && in_array($value, array_column($cards, 'id'), true) ? $value : null;
    }
}
