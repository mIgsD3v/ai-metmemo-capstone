<?php

use App\Models\ContactNote;
use App\Models\User;
use App\Services\Ai\NotesIndexer;
use App\Services\ContactService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('metmemo:remind-followups', function (ContactService $contacts) {
    $sent = 0;

    User::query()->whereHas('contacts', fn ($query) => $query->whereDate('next_follow_up_at', '<=', today()))
        ->each(function (User $user) use ($contacts, &$sent) {
            $sent += (int) $contacts->sendFollowUpReminder($user);
        });

    $this->info("Sent {$sent} follow-up ".str('reminder')->plural($sent).'.');
})->purpose("Notify each user of today's due and overdue follow-ups (once a day)");

Artisan::command('metmemo:index-notes {--user= : Only this user\'s notes (their id)}', function (NotesIndexer $indexer) {
    $query = ContactNote::query()
        ->with('contact:id,user_id,full_name')
        ->when($this->option('user'), fn ($q, $user) => $q->whereHas('contact', fn ($c) => $c->where('user_id', $user)))
        ->orderBy('id');

    $total = (clone $query)->count();
    $indexed = 0;

    $query->chunk(NotesIndexer::BATCH, function ($notes) use ($indexer, &$indexed) {
        $indexed += $indexer->index($notes);
    });

    $this->info("Indexed {$indexed} of {$total} notes.");

    return $indexed === $total ? 0 : 1;
})->purpose("Send every meeting note to the assistant's search index (first setup, or to catch up after an outage)");

// Users are in the Philippines: the morning summary goes out at 8 AM Manila time.
Schedule::command('metmemo:remind-followups')->dailyAt('08:00')->timezone('Asia/Manila');
