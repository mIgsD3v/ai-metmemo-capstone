<?php

namespace App\Services;

use App\Enums\ContactSource;
use App\Models\Card;
use App\Models\Contact;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The numbers behind the Insights page. Each answers a question that leads to a decision:
 * is my networking growing, which events and cards bring people in, and am I following up in time.
 */
class InsightsService
{
    /** Range choices, in weeks: about a month, a quarter, half a year. */
    public const RANGES = [4, 12, 26];

    /** Events shown by name; the rest are summed as "Other events". */
    private const TOP_EVENTS = 8;

    public function forUser(User $user, int $weeks): array
    {
        // Whole weeks (Monday to Sunday), ending with this one.
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1);
        $previousStart = $start->subWeeks($weeks);

        $inRange = fn () => $user->contacts()->where('created_at', '>=', $start);
        $inPrevious = fn () => $user->contacts()->where('created_at', '>=', $previousStart)->where('created_at', '<', $start);

        return [
            'range' => ['weeks' => $weeks, 'from' => $start->toDateString(), 'to' => today()->toDateString()],
            'totals' => [
                'contacts' => $inRange()->count(),
                'contactsBefore' => $inPrevious()->count(),
                'fromCards' => $inRange()->where('source', ContactSource::LeadForm)->count(),
                'fromCardsBefore' => $inPrevious()->where('source', ContactSource::LeadForm)->count(),
            ],
            'weekly' => $this->weekly($inRange(), $start, $weeks),
            'events' => $this->events($inRange()),
            'cards' => $this->cards($user, $start),
            'followUps' => $this->followUps($user, $inRange()),
        ];
    }

    /** New contacts per week, split by how they were added. Every week appears, even empty ones. */
    private function weekly($query, CarbonImmutable $start, int $weeks): array
    {
        $empty = array_fill_keys(array_map(fn (ContactSource $source) => $source->value, ContactSource::cases()), 0);
        $buckets = [];

        for ($i = 0; $i < $weeks; $i++) {
            $buckets[$start->addWeeks($i)->toDateString()] = $empty;
        }

        foreach ($query->select(['created_at', 'source'])->cursor() as $contact) {
            $week = CarbonImmutable::parse($contact->created_at)->startOfWeek()->toDateString();
            if (isset($buckets[$week])) {
                $buckets[$week][$contact->source->value]++;
            }
        }

        return array_map(fn (string $week, array $counts) => ['week' => $week, ...$counts], array_keys($buckets), $buckets);
    }

    /** Which events bring in the most people: the top few by name, the rest together. */
    private function events($query): array
    {
        $counts = $query->whereNotNull('event')->where('event', '!=', '')
            ->selectRaw('event, count(*) as total')
            ->groupBy('event')
            ->orderByDesc('total')
            ->orderBy('event')
            ->pluck('total', 'event')
            ->map(fn ($total) => (int) $total);

        return [
            'top' => $counts->take(self::TOP_EVENTS)->map(fn ($total, $event) => ['label' => $event, 'total' => $total])->values(),
            'other' => $counts->skip(self::TOP_EVENTS)->sum(),
            'otherCount' => max(0, $counts->count() - self::TOP_EVENTS),
        ];
    }

    /** Leads per card in the range, including cards that brought none (that's worth knowing too). */
    private function cards(User $user, CarbonImmutable $start): array
    {
        $cards = $user->cards()
            ->withCount(['contacts' => fn ($query) => $query->where('created_at', '>=', $start)])
            ->orderByDesc('contacts_count')
            ->latest()
            ->get();

        // Cards often share a name; the company tells them apart, numbered if even that repeats.
        $seen = [];

        return $cards->map(function (Card $card) use (&$seen) {
            $label = $card->company ?: ($card->job_title ?: $card->full_name);
            $seen[$label] = ($seen[$label] ?? 0) + 1;

            return [
                'id' => $card->id,
                'label' => $seen[$label] > 1 ? "{$label} ({$seen[$label]})" : $label,
                'public' => $card->is_public,
                'total' => $card->contacts_count,
            ];
        })->all();
    }

    /**
     * Follow-up health right now (all contacts), and how fast you follow up (contacts met in the range).
     */
    private function followUps(User $user, $inRange): array
    {
        $today = today();
        $due = fn () => $user->contacts()->whereNotNull('next_follow_up_at');

        $met = $inRange->get(['met_at', 'created_at', 'first_followed_up_at']);
        $followed = $met->whereNotNull('first_followed_up_at');
        $days = $followed->map(function (Contact $contact) {
            $from = ($contact->met_at ?? $contact->created_at)->copy()->startOfDay();

            return max(0, $from->diffInDays($contact->first_followed_up_at->copy()->startOfDay()));
        });

        return [
            'health' => [
                'overdue' => $due()->whereDate('next_follow_up_at', '<', $today)->count(),
                'today' => $due()->whereDate('next_follow_up_at', $today)->count(),
                'upcoming' => $due()->whereDate('next_follow_up_at', '>', $today)->count(),
                'none' => $user->contacts()->whereNull('next_follow_up_at')->count(),
            ],
            'followedUp' => $followed->count(),
            'met' => $met->count(),
            'averageDays' => $days->isEmpty() ? null : round($days->avg(), 1),
        ];
    }
}
