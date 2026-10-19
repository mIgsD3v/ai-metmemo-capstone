<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContactNoteController extends Controller
{
    public function __construct(
        private readonly ContactService $contacts,
    ) {}

    public function store(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->contacts->addNote($contact, $validated['body']);

        return back()->with('flash.banner', 'Note added.');
    }
}
