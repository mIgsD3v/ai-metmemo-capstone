<?php

namespace App\Notifications;

/** Morning summary: one notification for the day, never one per contact. */
class FollowUpsDue extends MetMemoNotification
{
    /**
     * @param  array<int, string>  $names  Up to a few names to show, most overdue first.
     */
    public function __construct(
        private readonly int $total,
        private readonly int $overdue,
        private readonly array $names,
    ) {}

    protected function payload(): array
    {
        $people = $this->total === 1 ? '1 follow-up' : "{$this->total} follow-ups";
        $overdue = $this->overdue > 0 ? " ({$this->overdue} overdue)" : '';
        $others = $this->total - count($this->names);

        return [
            'kind' => 'followups',
            'title' => "{$people} due today{$overdue}",
            'body' => implode(', ', $this->names).($others > 0 ? " and {$others} more" : '').'.',
            'url' => route('dashboard'),
        ];
    }
}
