<?php

use App\Models\User;
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

// Users are in the Philippines: the morning summary goes out at 8 AM Manila time.
Schedule::command('metmemo:remind-followups')->dailyAt('08:00')->timezone('Asia/Manila');
