<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the assistant's tools: get_overdue_followups, find_contact, get_contact_notes.
 * Email and phone are left out on purpose: drafting a follow-up never needs them.
 */
class AssistantDataController extends Controller
{
    public function __construct(
        private readonly ContactService $contacts,
    ) {}

    /** Contacts due within the next N days, overdue ones included (oldest first). */
    public function followups(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['days' => ['sometimes', 'integer', 'min:0', 'max:90']]);
        $days = (int) ($validated['days'] ?? 7);

        return response()->json([
            'as_of' => today()->toDateString(),
            'days' => $days,
            'contacts' => $this->contacts->dueForFollowUp($user, $days)->map($this->contact(...))->values(),
        ]);
    }

    public function contacts(Request $request, User $user): JsonResponse
    {
        $search = $request->validate(['search' => ['required', 'string', 'max:100']])['search'];

        return response()->json([
            'contacts' => $this->contacts->search($user, $search)->map($this->contact(...))->values(),
        ]);
    }

    public function notes(User $user, Contact $contact): JsonResponse
    {
        return response()->json([
            'contact' => $this->contact($contact),
            'notes' => $this->contacts->notes($contact)->map(fn (ContactNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'created_at' => $note->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    private function contact(Contact $contact): array
    {
        $followUp = $contact->next_follow_up_at;

        return [
            'id' => $contact->id,
            'full_name' => $contact->full_name,
            'company' => $contact->company,
            'job_title' => $contact->job_title,
            'event' => $contact->event,
            'tags' => $contact->tags ?? [],
            'met_at' => $contact->met_at?->toDateString(),
            'next_follow_up_at' => $followUp?->toDateString(),
            'days_overdue' => $followUp && $followUp->lt(today()) ? (int) $followUp->diffInDays(today()) : 0,
            'last_contacted_at' => $contact->last_contacted_at?->toIso8601String(),
        ];
    }
}
