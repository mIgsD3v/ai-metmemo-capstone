<?php

namespace App\Services;

use App\Enums\ContactSource;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Card;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * App-wide numbers for the admin Overview. Counts only: never the contents of anyone's contacts,
 * notes or chats (see the Privacy Policy).
 */
class AdminStatsService
{
    public const DAYS = 30;

    public function overview(): array
    {
        $today = CarbonImmutable::today();
        $since = $today->subDays(self::DAYS - 1);

        return [
            'users' => [
                'total' => User::query()->count(),
                'new7' => User::query()->where('created_at', '>=', $today->subDays(6))->count(),
                'new30' => User::query()->where('created_at', '>=', $since)->count(),
                'newBefore' => User::query()->whereBetween('created_at', [$since->subDays(self::DAYS), $since])->count(),
                'active30' => $this->activeSince($since),
                'google' => User::query()->whereNotNull('google_id')->count(),
                'twoFactor' => User::query()->whereNotNull('two_factor_confirmed_at')->count(),
            ],
            'signups' => $this->signupsPerDay($since, $today),
            'content' => [
                'cards' => Card::query()->count(),
                'publicCards' => Card::query()->where('is_public', true)->count(),
                'contacts' => Contact::query()->count(),
                'notes' => ContactNote::query()->count(),
                'conversations' => AssistantConversation::query()->count(),
                'questions' => AssistantMessage::query()->where('role', 'user')->count(),
            ],
            'sources' => $this->contactsBySource(),
            'roles' => Role::query()->withCount('users')->orderBy('name')->get()
                ->map(fn (Role $role) => ['name' => $role->name, 'users' => $role->users_count]),
            'recent' => User::query()->with('roles')->latest()->take(6)->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'photo_url' => $user->profile_photo_url,
                'google' => $user->google_id !== null,
                'role' => $user->roles->first()?->name,
                'created_at' => $user->created_at->toDateString(),
            ]),
        ];
    }

    /** People who added a contact, edited a card or used the assistant since the given day. */
    private function activeSince(CarbonImmutable $since): int
    {
        return Contact::query()->where('created_at', '>=', $since)->distinct()->pluck('user_id')
            ->merge(Card::query()->where('updated_at', '>=', $since)->distinct()->pluck('user_id'))
            ->merge(AssistantConversation::query()->where('updated_at', '>=', $since)->distinct()->pluck('user_id'))
            ->unique()
            ->count();
    }

    /** @return list<array{date: string, count: int}> every day present, oldest first */
    private function signupsPerDay(CarbonImmutable $since, CarbonImmutable $today): array
    {
        $counts = User::query()->where('created_at', '>=', $since)->pluck('created_at')
            ->countBy(fn ($createdAt) => $createdAt->toDateString());

        $days = [];
        for ($day = $since; $day->lte($today); $day = $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'count' => $counts[$day->toDateString()] ?? 0];
        }

        return $days;
    }

    private function contactsBySource(): array
    {
        $counts = Contact::query()->toBase()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source');

        return array_map(fn (ContactSource $source) => [
            'key' => $source->value,
            'label' => $source->label(),
            'count' => (int) ($counts[$source->value] ?? 0),
        ], ContactSource::cases());
    }
}
