<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every MetMemo notification is saved (the bell's list), pushed live over Pusher (the bell's badge and a toast)
 * and emailed. Each one is a small {kind, title, body, url} payload that the bell and the email render.
 */
abstract class MetMemoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array{kind: string, title: string, body: string, url: string} */
    abstract protected function payload(): array;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // No Pusher keys (local dev, tests): still saved, so the bell shows it on the next page load.
        $channels = config('broadcasting.default') === 'pusher' ? ['database', 'broadcast'] : ['database'];

        // The log mailer would write contact names into the logs, so it counts as no mailer.
        return config('mail.default') === 'log' ? $channels : [...$channels, 'mail'];
    }

    /**
     * The bell stays instant. Only the email waits on the queue, so a visitor never waits on the mail server.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync', 'broadcast' => 'sync'];
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

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage)
            ->subject($payload['title'])
            ->greeting('Hi '.str($notifiable->name)->before(' ').',')
            ->line($payload['body'])
            ->action($this->actionText(), $payload['url']);
    }

    /** The email's button. */
    protected function actionText(): string
    {
        return 'View contact';
    }
}
