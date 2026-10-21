<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Every MetMemo notification is saved (the bell's list) and pushed live over Pusher (the bell's badge and a toast).
 * Each one is a small {kind, title, body, url} payload that the bell renders.
 */
abstract class MetMemoNotification extends Notification
{
    /** @return array{kind: string, title: string, body: string, url: string} */
    abstract protected function payload(): array;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // No Pusher keys (local dev, tests): still saved, so the bell shows it on the next page load.
        return config('broadcasting.default') === 'pusher' ? ['database', 'broadcast'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // Sent right away rather than queued, so it works without a queue worker running.
        return (new BroadcastMessage($this->payload()))->onConnection('sync');
    }
}
