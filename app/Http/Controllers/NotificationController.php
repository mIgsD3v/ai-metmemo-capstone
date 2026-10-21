<?php

namespace App\Http\Controllers;

use App\Notifications\FollowUpsDue;
use App\Notifications\NewLeadCaptured;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/** The bell's menu, the full Notifications page, and opening / marking them read. */
class NotificationController extends Controller
{
    /** The bell shows this many in its scrolling list; older ones are on the Notifications page. */
    public const MENU_LIMIT = 50;

    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /** The "All types" filter: its key in the URL => the notification class. */
    private const TYPES = [
        'lead' => NewLeadCaptured::class,
        'followups' => FollowUpsDue::class,
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $type = array_key_exists((string) $request->query('type'), self::TYPES) ? $request->query('type') : null;
        $perPage = in_array((int) $request->query('per_page'), self::PER_PAGE_OPTIONS, true) ? (int) $request->query('per_page') : self::PER_PAGE_OPTIONS[0];

        $notifications = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->when($type, fn ($query, string $type) => $query->where('type', self::TYPES[$type]))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification) => self::present($notification));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => ['filter' => $filter, 'type' => $type, 'per_page' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'counts' => [
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /** The bell's list, fetched when it opens (so pages don't carry 50 rows they rarely show). */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->take(self::MENU_LIMIT)->get()->map(self::present(...)),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        // Looked up through the user's own notifications, so nobody can open someone else's.
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /** One shape for the bell, the page, and live (Pusher) arrivals. */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            ...$notification->data,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
