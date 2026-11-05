<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { timeAgo } from '@/dates';
import { loadNotifications, markAllRead, markRead, notificationIcons, notifications } from '@/notifications';

const isOpen = ref(false);
const root = ref(null);

const toggle = () => {
    isOpen.value = ! isOpen.value;
    if (isOpen.value) loadNotifications();
};

const close = () => { isOpen.value = false; };

// Closes on a click anywhere else or Escape. (A full-screen overlay can't be used: the glass top
// bar's backdrop blur would trap it inside the bar.)
const onPointerDown = (event) => {
    if (isOpen.value && root.value && ! root.value.contains(event.target)) close();
};
const onKeydown = (event) => {
    if (event.key === 'Escape') close();
};

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeydown);
});
onUnmounted(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    document.removeEventListener('keydown', onKeydown);
});

const open = (item) => {
    markRead(item);
    close();
    router.post(route('notifications.open', item.id));
};

const readAll = () => {
    markAllRead();
    router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 hover:text-gray-900 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            :class="{ 'bg-gray-50 text-gray-900': isOpen }"
            :aria-label="notifications.unread ? `Notifications, ${notifications.unread} unread` : 'Notifications'"
            :aria-expanded="isOpen"
            aria-haspopup="dialog"
            @click="toggle"
        >
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="notificationIcons.bell" />
            </svg>
            <span
                v-if="notifications.unread"
                class="absolute top-1 end-1 min-w-4 h-4 px-1 rounded-full bg-accent text-accent-fg text-[10px] font-semibold leading-4 text-center ring-2 ring-page"
                aria-hidden="true"
            >
                {{ notifications.unread > 9 ? '9+' : notifications.unread }}
            </span>
        </button>

        <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition ease-in duration-100"
            leave-to-class="opacity-0"
        >
            <!-- Phones: a full-width sheet under the top bar, clear of the bottom nav. Larger screens: a menu under the bell. -->
            <div
                v-show="isOpen"
                class="z-50 flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl ring-1 ring-black/5 max-sm:fixed max-sm:inset-x-2 max-sm:top-full max-sm:mt-1.5 max-sm:max-h-[calc(100dvh-10rem)] sm:absolute sm:end-0 sm:mt-2 sm:w-[23rem] sm:max-h-[min(34rem,80vh)]"
                role="dialog"
                aria-label="Notifications"
            >
                <div class="flex shrink-0 items-center justify-between gap-3 px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <h2 class="text-[15px] font-semibold text-gray-900">Notifications</h2>
                        <span v-if="notifications.unread" class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600 tabular-nums">
                            {{ notifications.unread }} new
                        </span>
                    </div>
                    <button v-if="notifications.unread" type="button" class="rounded-md px-1.5 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50" @click="readAll">
                        Mark all as read
                    </button>
                </div>

                <!-- Up to 50, scrolling. Older ones are on the Notifications page. -->
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <div v-if="! notifications.loaded" class="divide-y divide-gray-200" aria-label="Loading">
                        <div v-for="row in 3" :key="row" class="flex gap-3 px-4 py-3 animate-pulse">
                            <div class="size-9 shrink-0 rounded-full bg-gray-100" />
                            <div class="flex-1 space-y-2 pt-1">
                                <div class="h-3 w-2/3 rounded bg-gray-100" />
                                <div class="h-2.5 w-full rounded bg-gray-100" />
                                <div class="h-2 w-16 rounded bg-gray-100" />
                            </div>
                        </div>
                    </div>

                    <ul v-else-if="notifications.items.length" class="divide-y divide-gray-200">
                        <li v-for="item in notifications.items" :key="item.id">
                            <button
                                type="button"
                                class="flex w-full items-start gap-3 px-4 py-3 text-start transition hover:bg-gray-50 active:bg-gray-50"
                                :class="{ 'bg-indigo-50/40': ! item.read }"
                                @click="open(item)"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                    <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="notificationIcons[item.kind] ?? notificationIcons.bell" />
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="text-sm leading-snug text-gray-900 line-clamp-1" :class="item.read ? 'font-medium' : 'font-semibold'">{{ item.title }}</span>
                                        <span v-if="! item.read" class="mt-1.5 size-2 shrink-0 rounded-full bg-indigo-600" aria-label="Unread" />
                                    </span>
                                    <span class="mt-0.5 block text-[13px] leading-snug text-gray-500 line-clamp-2">{{ item.body }}</span>
                                    <span class="mt-1 block text-xs text-gray-400">{{ timeAgo(item.created_at) }}</span>
                                </span>
                            </button>
                        </li>
                    </ul>

                    <div v-else class="px-6 py-10 text-center">
                        <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="notificationIcons.bell" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-medium text-gray-900">You're all caught up</p>
                        <p class="mt-1 text-xs text-gray-500">New leads, card swaps and your morning follow-up summary show up here.</p>
                    </div>
                </div>

                <Link
                    :href="route('notifications.index')"
                    class="block shrink-0 border-t border-gray-200 px-4 py-2.5 text-center text-sm font-medium text-indigo-600 hover:bg-gray-50"
                    @click="close"
                >
                    View all notifications
                </Link>
            </div>
        </transition>
    </div>
</template>
