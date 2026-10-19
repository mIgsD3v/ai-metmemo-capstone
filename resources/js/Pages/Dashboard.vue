<script setup>
import { today as localToday } from '@/dates';
import { computed } from 'vue';
import { Deferred, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import QrCode from '@/Components/QrCode.vue';
import { copyText } from '@/clipboard';

const props = defineProps({
    stats: Object,
    dueContacts: Array,
    recentContacts: Array,
    shareCard: Object,
    aiOnline: Boolean,
});

const page = usePage();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);

const greeting = (() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
})();

const todayLabel = new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
const today = localToday();

// Dates arrive as YYYY-MM-DD; read them as local days so they don't shift by timezone.
const formatDay = (value) => {
    if (! value) return '';
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

const dueLabel = (value) => {
    const day = value.slice(0, 10);
    if (day < today) return { text: `Overdue · ${formatDay(day)}`, overdue: true };
    if (day === today) return { text: 'Today', overdue: false };
    return { text: formatDay(day), overdue: false };
};

const sourceLabels = { manual: 'Added by hand', lead_form: 'Card form', scan: 'Scanned' };

const stats = computed(() => [
    { label: 'Contacts', value: props.stats.contacts, href: route('contacts.index') },
    { label: 'New this week', value: props.stats.thisWeek, href: route('contacts.index') },
    { label: 'Due for follow-up', value: props.stats.due, href: route('contacts.index'), attention: props.stats.due > 0 },
    { label: 'Cards', value: props.stats.cards, href: route('cards.index') },
]);

const copyLink = () => copyText(props.shareCard.public_url);

const initials = (name) => name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="py-6 sm:py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Greeting -->
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-500">{{ todayLabel }}</p>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">{{ greeting }}, {{ firstName }}</h1>
                    </div>
                    <Deferred data="aiOnline">
                        <template #fallback>
                            <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-500">
                                <span class="size-2 rounded-full bg-gray-300" /> Checking AI…
                            </span>
                        </template>
                        <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-xs" :class="aiOnline ? 'text-gray-700' : 'text-gray-500'">
                            <span class="size-2 rounded-full" :class="aiOnline ? 'bg-green-500' : 'bg-red-500'" />
                            AI {{ aiOnline ? 'online' : 'offline' }}
                        </span>
                    </Deferred>
                </div>

                <!-- Scan call to action -->
                <section class="relative overflow-hidden rounded-2xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 p-6 sm:p-8">
                    <div class="absolute inset-0 bg-grid opacity-60 pointer-events-none" aria-hidden="true" />
                    <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                        <div class="space-y-2 max-w-xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400">AI card scanner</p>
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Met someone? Scan their card.</h2>
                            <p class="text-sm text-gray-300 dark:text-gray-600">Take a photo of a paper business card. The AI reads it, flags anything it is unsure about, and you confirm before it is saved.</p>
                        </div>
                        <div class="flex flex-col sm:items-end gap-2 shrink-0">
                            <Link :href="route('scan.create')" class="btn-accent text-base px-6 py-3 gap-2">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                </svg>
                                Scan a card
                            </Link>
                            <Link :href="route('contacts.create')" class="text-sm text-gray-300 dark:text-gray-600 hover:underline text-center">or add a contact by hand</Link>
                        </div>
                    </div>
                </section>

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <Link
                        v-for="stat in stats"
                        :key="stat.label"
                        :href="stat.href"
                        class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 hover:border-gray-300 transition"
                    >
                        <div class="text-xs sm:text-sm text-gray-500">{{ stat.label }}</div>
                        <div class="mt-1 text-2xl sm:text-3xl font-semibold tabular-nums" :class="stat.attention ? 'text-amber-600' : 'text-gray-900'">{{ stat.value }}</div>
                    </Link>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Due for follow-up -->
                    <section class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl">
                        <div class="flex items-center justify-between px-5 sm:px-6 pt-5">
                            <h3 class="font-semibold text-gray-900">Due for follow-up</h3>
                            <Link :href="route('contacts.index')" class="text-sm text-indigo-600 hover:underline">All contacts</Link>
                        </div>
                        <div v-if="! dueContacts.length" class="px-5 sm:px-6 py-10 text-center">
                            <p class="font-medium text-gray-700">You're all caught up.</p>
                            <p class="text-sm text-gray-500 mt-1">New contacts get a follow-up date two days after you meet.</p>
                        </div>
                        <ul v-else class="mt-3 divide-y divide-gray-200">
                            <li v-for="contact in dueContacts" :key="contact.id">
                                <Link :href="route('contacts.show', contact.id)" class="flex items-center gap-3 px-5 sm:px-6 py-3 hover:bg-gray-50 transition">
                                    <span class="flex items-center justify-center size-10 shrink-0 rounded-full bg-indigo-50 text-indigo-600 text-sm font-semibold">{{ initials(contact.full_name) }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-gray-900 truncate">{{ contact.full_name }}</span>
                                        <span class="block text-sm text-gray-500 truncate">{{ contact.company || 'No company' }}</span>
                                    </span>
                                    <span
                                        class="shrink-0 text-xs font-medium rounded-full px-2.5 py-1"
                                        :class="dueLabel(contact.next_follow_up_at).overdue
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'
                                            : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ dueLabel(contact.next_follow_up_at).text }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </section>

                    <!-- Share your card -->
                    <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col">
                        <h3 class="font-semibold text-gray-900">Share your card</h3>
                        <template v-if="shareCard">
                            <p class="text-sm text-gray-500 mt-1">Let them scan this with their phone camera.</p>
                            <div class="mt-4 flex justify-center">
                                <div class="rounded-xl bg-white p-2 border border-gray-200">
                                    <QrCode :value="shareCard.public_url" :size="168" />
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <div class="font-medium text-gray-900">{{ shareCard.full_name }}</div>
                                <div class="text-sm text-gray-500">{{ [shareCard.job_title, shareCard.company].filter(Boolean).join(' · ') }}</div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition" @click="copyLink">
                                    Copy link
                                </button>
                                <a :href="shareCard.public_url" target="_blank" rel="noopener" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                                    Open page
                                </a>
                            </div>
                        </template>
                        <div v-else class="flex-1 flex flex-col items-center justify-center text-center py-8 gap-3">
                            <p class="text-sm text-gray-500">Create a card to get your link and QR code.</p>
                            <Link :href="route('cards.create')" class="btn-accent">Create your card</Link>
                        </div>
                    </section>
                </div>

                <!-- Recent contacts -->
                <section class="bg-white border border-gray-200 rounded-2xl">
                    <div class="flex items-center justify-between px-5 sm:px-6 pt-5">
                        <h3 class="font-semibold text-gray-900">Recently added</h3>
                        <Link :href="route('contacts.index')" class="text-sm text-indigo-600 hover:underline">View all</Link>
                    </div>
                    <div v-if="! recentContacts.length" class="px-5 sm:px-6 py-10 text-center text-sm text-gray-500">
                        No contacts yet. Scan a card or share your QR code at your next meeting.
                    </div>
                    <ul v-else class="mt-3 divide-y divide-gray-200">
                        <li v-for="contact in recentContacts" :key="contact.id">
                            <Link :href="route('contacts.show', contact.id)" class="flex items-center gap-3 px-5 sm:px-6 py-3 hover:bg-gray-50 transition">
                                <span class="flex items-center justify-center size-10 shrink-0 rounded-full bg-gray-100 text-gray-600 text-sm font-semibold">{{ initials(contact.full_name) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-gray-900 truncate">{{ contact.full_name }}</span>
                                    <span class="block text-sm text-gray-500 truncate">
                                        {{ sourceLabels[contact.source] }}<template v-if="contact.card"> · via {{ contact.card }}</template>
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs text-gray-400 tabular-nums">{{ formatDay(contact.created_at) }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
