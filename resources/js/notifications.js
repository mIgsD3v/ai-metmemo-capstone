import { reactive } from 'vue';
import { toast } from '@/toast';

/**
 * The bell's state, shared by every page. The unread count comes with each page load (the shared
 * "notifications" prop); the list loads when the bell opens; Pusher updates both live in between.
 */
export const notifications = reactive({ items: [], unread: 0, loaded: false, loading: false });

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
