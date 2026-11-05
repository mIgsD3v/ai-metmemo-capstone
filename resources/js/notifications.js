import { reactive } from 'vue';
import { toast } from '@/toast';

/**
 * The bell's state, shared by every page. The unread count comes with each page load (the shared
 * "notifications" prop); the list loads when the bell opens; Pusher updates both live in between.
 */
export const notifications = reactive({ items: [], unread: 0, loaded: false, loading: false });

/** One icon per notification kind, shared by the bell and the Notifications page. */
export const notificationIcons = {
    lead: 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z',
    followups: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    swap: 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
    bell: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
};

// Matches NotificationController::MENU_LIMIT.
const MENU_LIMIT = 50;
let echo = null;
let listeningAs = null;

/** Called with each page's props: refresh the count, and connect (or disconnect) the live feed. */
export function syncNotifications(props) {
    if (props?.notifications) {
        notifications.unread = props.notifications.unread;
    }

    const userId = props?.auth?.user?.id ?? null;

    if (userId !== listeningAs) {
        stopListening();
        notifications.items = [];
        notifications.loaded = false;
        if (userId && props.pusher) listen(userId, props.pusher);
    }
}

/** Fetch the latest for the bell's list. Called each time it opens, so it's never stale. */
export async function loadNotifications() {
    notifications.loading = true;

    try {
        const { data } = await window.axios.get(route('notifications.recent'));
        notifications.items = data.items;
        notifications.unread = data.unread;
        notifications.loaded = true;
    } finally {
        notifications.loading = false;
    }
}

export function markRead(item) {
    if (item.read) return;
    item.read = true;
    notifications.unread = Math.max(0, notifications.unread - 1);
}

export function markAllRead() {
    notifications.items.forEach((item) => { item.read = true; });
    notifications.unread = 0;
}

async function listen(userId, pusher) {
    listeningAs = userId;

    // Loaded only for signed-in users with Pusher set up, so public pages stay light.
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);

    if (listeningAs !== userId) return; // Logged out while loading.

    echo = new Echo({
        broadcaster: 'pusher',
        client: new Pusher(pusher.key, {
            cluster: pusher.cluster,
            forceTLS: true,
            // Private channel sign-in through Laravel, using the session (axios sends the CSRF token).
            channelAuthorization: {
                customHandler: ({ socketId, channelName }, callback) => {
                    window.axios.post('/broadcasting/auth', { socket_id: socketId, channel_name: channelName })
                        .then(({ data }) => callback(null, data))
                        .catch((error) => callback(error, null));
                },
            },
        }),
    });

    echo.private(`App.Models.User.${userId}`).notification((incoming) => {
        notifications.items.unshift({
            id: incoming.id,
            kind: incoming.kind,
            title: incoming.title,
            body: incoming.body,
            url: incoming.url,
            read: false,
            created_at: new Date().toISOString(),
        });
        notifications.items.splice(MENU_LIMIT);
        notifications.unread += 1;
        toast(incoming.body, 'info', 6000);
    });
}

function stopListening() {
    echo?.disconnect();
    echo = null;
    listeningAs = null;
}
