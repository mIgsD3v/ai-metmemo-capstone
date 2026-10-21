<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { timeAgo } from '@/dates';
import { loadNotifications, markAllRead, markRead, notifications } from '@/notifications';

const bell = 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0';

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
                <path stroke-linecap="round" stroke-linejoin="round" :d="bell" />
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
            <!-- Phones: a full-width panel under the top bar. Larger screens: a menu under the bell. -->
            <div
                v-show="isOpen"
                class="z-50 flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl max-sm:fixed max-sm:inset-x-3 max-sm:top-full max-sm:mt-2 sm:absolute sm:end-0 sm:mt-2 sm:w-96"
                role="dialog"
                aria-label="Notifications"
            >
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold text-gray-900">Notifications</h2>
                        <span v-if="notifications.unread" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600 tabular-nums">
                            {{ notifications.unread }} new
                        </span>
                    </div>
                    <button v-if="notifications.unread" type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="readAll">
                        Mark all as read
                    </button>
                </div>

                <!-- Up to 50, scrolling. Older ones are on the Notifications page. -->
                <div class="max-h-[min(28rem,65vh)] overflow-y-auto overscroll-contain">
                    <div v-if="! notifications.loaded" class="divide-y divide-gray-200" aria-label="Loading">
                        <div v-for="row in 3" :key="row" class="px-5 py-4 ps-11 space-y-2 animate-pulse">
                            <div class="h-3.5 w-2/3 rounded bg-gray-100" />
                            <div class="h-3 w-full rounded bg-gray-100" />
                            <div class="h-2.5 w-20 rounded bg-gray-100" />
                        </div>
                    </div>

                    <ul v-else-if="notifications.items.length" class="divide-y divide-gray-200">
                        <li v-for="item in notifications.items" :key="item.id">
                            <button
                                type="button"
                                class="relative block w-full px-5 py-4 ps-11 text-start transition hover:bg-gray-50"
                                :class="{ 'bg-indigo-50/40': ! item.read }"
                                @click="open(item)"
                            >
                                <span v-if="! item.read" class="absolute start-5 top-[1.4rem] size-2 rounded-full bg-indigo-600" aria-label="Unread" />
                                <span class="block text-sm font-semibold text-gray-900">{{ item.title }}</span>
                                <span class="mt-1 block text-sm leading-relaxed text-gray-600">{{ item.body }}</span>
                                <span class="mt-2 block text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ timeAgo(item.created_at) }}</span>
                            </button>
                        </li>
                    </ul>

                    <div v-else class="px-6 py-12 text-center">
                        <svg class="mx-auto size-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="bell" />
                        </svg>
                        <p class="mt-3 text-sm font-medium text-gray-900">You're all caught up</p>
                        <p class="mt-1 text-xs text-gray-500">New leads from your cards and your morning follow-up summary show up here.</p>
                    </div>
                </div>

                <Link
                    :href="route('notifications.index')"
                    class="block border-t border-gray-200 bg-gray-50 px-5 py-3 text-center text-sm font-medium text-indigo-600 hover:underline"
                    @click="close"
                >
                    View all notifications
                </Link>
            </div>
        </transition>
    </div>
</template>
