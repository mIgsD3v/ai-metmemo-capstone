<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { timeAgo } from '@/dates';
import { markAllRead } from '@/notifications';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    notifications: Object,
    filters: Object,
    perPageOptions: Array,
    counts: Object,
});

const icons = {
    lead: 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z',
    followups: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    swap: 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
    bell: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
};

const typeLabels = { lead: 'New lead', followups: 'Follow-up', swap: 'Card swap' };

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'unread', label: 'Unread' },
];

const type = ref(props.filters.type ?? '');

// Changing the tab or type starts again at page 1; the page size is kept.
const reload = ({ filter = props.filters.filter, page, perPage = props.filters.per_page } = {}) => {
    router.get(route('notifications.index'), {
        filter: filter === 'all' ? undefined : filter,
        type: type.value || undefined,
        per_page: perPage === props.perPageOptions[0] ? undefined : perPage,
        page: page > 1 ? page : undefined,
    }, { preserveState: true, preserveScroll: ! page, replace: ! page });
};

watch(type, () => reload());

const open = (item) => router.post(route('notifications.open', item.id));

const readAll = () => {
    markAllRead();
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};

const formatDate = (iso) => new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <AppLayout title="Notifications">
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <div class="min-w-0">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notifications</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ counts.unread ? `${counts.unread} unread` : 'All caught up' }}</p>
                </div>
                <button v-if="counts.unread" type="button" class="btn-accent shrink-0" @click="readAll">
                    <span class="sm:hidden">Read all</span>
                    <span class="hidden sm:inline">Mark all as read</span>
                </button>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="page space-y-4">
                <!-- Filters -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex rounded-md border border-gray-300 bg-white p-1 shadow-sm sm:w-auto" role="tablist" aria-label="Show">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="filters.filter === tab.key"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded px-4 py-1.5 text-sm font-medium transition"
                            :class="filters.filter === tab.key ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-900'"
                            @click="reload({ filter: tab.key })"
                        >
                            {{ tab.label }}
                            <span class="rounded-full px-1.5 text-xs tabular-nums" :class="tab.key === 'unread' && counts.unread ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'">
                                {{ counts[tab.key] }}
                            </span>
                        </button>
                    </div>
                    <select
                        v-model="type"
                        class="w-full sm:w-72 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Filter by type"
                    >
                        <option value="">All types</option>
                        <option value="lead">New leads from your cards</option>
                        <option value="followups">Follow-up reminders</option>
                    </select>
                </div>

                <!-- Phone: list -->
                <ul class="md:hidden bg-white border border-gray-200 rounded-2xl divide-y divide-gray-200 overflow-hidden">
                    <li v-for="item in notifications.data" :key="item.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 px-4 py-3 text-start active:bg-gray-50"
                            :class="{ 'bg-indigo-50/40': ! item.read }"
                            @click="open(item)"
                        >
                            <span class="flex items-center justify-center size-10 shrink-0 rounded-full bg-indigo-50 text-indigo-600">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.kind] ?? icons.bell" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-start justify-between gap-2">
                                    <span class="text-sm text-gray-900" :class="item.read ? 'font-medium' : 'font-semibold'">{{ item.title }}</span>
                                    <span v-if="! item.read" class="mt-1.5 size-2 shrink-0 rounded-full bg-indigo-600" aria-label="Unread" />
                                </span>
                                <span class="mt-0.5 block text-sm text-gray-500 line-clamp-2">{{ item.body }}</span>
                                <span class="mt-1.5 block text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ timeAgo(item.created_at) }}</span>
                            </span>
                        </button>
                    </li>
                    <li v-if="! notifications.data.length" class="px-4 py-10 text-center text-sm text-gray-500">No notifications found.</li>
                </ul>

                <!-- Larger screens: table -->
                <div class="hidden md:block bg-white border border-gray-200 rounded-2xl overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3 font-medium">Notification</th>
                                <th class="px-5 py-3 font-medium">Message</th>
                                <th class="px-5 py-3 font-medium">Type</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Received</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="item in notifications.data"
                                :key="item.id"
                                class="cursor-pointer hover:bg-gray-50"
                                :class="{ 'bg-indigo-50/40': ! item.read }"
                                @click="open(item)"
                            >
                                <td class="px-5 py-3">
                                    <button type="button" class="flex items-center gap-3 text-start text-gray-900 hover:text-indigo-600" :class="item.read ? 'font-medium' : 'font-semibold'" @click.stop="open(item)">
                                        <span class="flex items-center justify-center size-8 shrink-0 rounded-full bg-indigo-50 text-indigo-600">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.kind] ?? icons.bell" />
                                            </svg>
                                        </span>
                                        <span class="whitespace-nowrap">{{ item.title }}</span>
                                    </button>
                                </td>
                                <td class="px-5 py-3 text-gray-600 min-w-64 max-w-md">
                                    <span class="line-clamp-2">{{ item.body }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ typeLabels[item.kind] ?? 'Other' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span v-if="! item.read" class="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-semibold text-indigo-600">
                                        <span class="size-2 rounded-full bg-indigo-600" aria-hidden="true" /> Unread
                                    </span>
                                    <span v-else class="text-xs text-gray-400">Read</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-gray-600 tabular-nums" :title="formatDate(item.created_at)">{{ timeAgo(item.created_at) }}</td>
                            </tr>
                            <tr v-if="! notifications.data.length">
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500">No notifications found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="notifications.total" class="bg-white border border-gray-200 rounded-2xl px-4 sm:px-5 py-3">
                    <Pagination :paginator="notifications" :per-page-options="perPageOptions" label="notifications" @change="reload" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
