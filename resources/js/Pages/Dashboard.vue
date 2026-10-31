<script setup>
import { today as localToday } from '@/dates';
import { computed, ref } from 'vue';
import { Deferred, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import FollowUpMenu from '@/Components/FollowUpMenu.vue';
import QrCode from '@/Components/QrCode.vue';
import { copyText } from '@/clipboard';

const props = defineProps({
    stats: Object,
    dueContacts: Array,
    recentContacts: Array,
    shareCards: Array,
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
const toDate = (value) => {
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
};

const formatDay = (value) => value ? toDate(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';

const daysBetween = (from, to) => Math.round((toDate(to) - toDate(from)) / 86_400_000);

const dueLabel = (value) => {
    const late = daysBetween(value, today);
    if (late > 0) return { text: `${late} ${late === 1 ? 'day' : 'days'} overdue`, overdue: true };
    if (late === 0) return { text: 'Due today', overdue: false };
    return { text: formatDay(value), overdue: false };
};

const addedLabel = (value) => {
    const ago = daysBetween(value, today);
    return ago === 0 ? 'Today' : ago === 1 ? 'Yesterday' : formatDay(value);
};

const waitingLine = computed(() => {
    const due = props.stats.due;
    if (! due) return "Nobody is waiting on a follow-up. You're all caught up.";
    return `${due} ${due === 1 ? 'person is' : 'people are'} waiting on a follow-up. Start with the oldest.`;
});

// Follow-ups: the oldest five (the server sorts them), split by tab.
const tabs = computed(() => [
    { key: 'overdue', label: 'Overdue', items: props.dueContacts.filter((c) => c.next_follow_up_at.slice(0, 10) < today) },
    { key: 'today', label: 'Due today', items: props.dueContacts.filter((c) => c.next_follow_up_at.slice(0, 10) === today) },
    { key: 'all', label: 'All', items: props.dueContacts },
]);
const tab = ref(props.dueContacts.some((c) => c.next_follow_up_at.slice(0, 10) < today) ? 'overdue' : 'all');
const shownDue = computed(() => tabs.value.find((t) => t.key === tab.value).items);

// "Draft email" opens the assistant with the request already asked.
const draftHref = (contact) => route('assistant.show', { ask: `Draft a follow-up email to ${contact.full_name}` });

const stats = computed(() => [
    {
        label: 'Contacts', value: props.stats.contacts, href: route('contacts.index'), icon: 'people',
        note: props.stats.thisWeek ? `+${props.stats.thisWeek} this week` : 'None new this week', good: props.stats.thisWeek > 0,
    },
    {
        label: 'Due for follow-up', value: props.stats.due, href: route('contacts.index'), icon: 'clock', attention: props.stats.due > 0,
        note: props.dueContacts.length ? `Oldest waiting since ${formatDay(props.dueContacts[0].next_follow_up_at)}` : 'All caught up',
    },
    // Compared with the same days last week, and the way into Insights (also on phones).
    { label: 'New this week', value: props.stats.thisWeek, href: route('insights'), icon: 'chart', delta: props.stats.thisWeek - props.stats.lastWeekSoFar },
    { label: 'Cards', value: props.stats.cards, href: route('cards.index'), icon: 'card', note: 'Shared by QR code and link' },
]);

const statIcons = {
    people: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    clock: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    chart: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
    card: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
};

const sources = {
    manual: { label: 'Added by hand', dot: 'bg-accent' },
    lead_form: { label: 'Card form', dot: 'bg-orange-500' },
    scan: { label: 'Scanned', dot: 'bg-blue-500' },
};

// The card being shared. The last one picked is remembered in this browser; otherwise the newest.
const SHARE_KEY = 'metmemo.shareCard';
const remembered = (() => {
    try { return localStorage.getItem(SHARE_KEY); } catch { return null; }
})();
const shareCardId = ref(remembered);
const shareCard = computed(() => props.shareCards.find((card) => card.id === shareCardId.value) ?? props.shareCards[0] ?? null);
const shareCardIndex = computed(() => props.shareCards.indexOf(shareCard.value));

const pickShareCard = (card) => {
    shareCardId.value = card.id;
    try { localStorage.setItem(SHARE_KEY, card.id); } catch { /* private window: just don't remember */ }
};

// Chip label: the company says which card it is. Cards with the same label get a number so they stay distinct.
const shareLabels = computed(() => {
    const seen = {};

    return Object.fromEntries(props.shareCards.map((card) => {
        const label = card.company || card.job_title || card.full_name;
        seen[label] = (seen[label] ?? 0) + 1;
        return [card.id, seen[label] > 1 ? `${label} (${seen[label]})` : label];
    }));
});

const copyLink = () => copyText(shareCard.value.public_url);

const initials = (name) => name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase();

// Each person keeps the same avatar colour everywhere: picked from their name.
const avatarColors = [
    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    'bg-violet-500/15 text-violet-700 dark:text-violet-300',
    'bg-teal-500/15 text-teal-700 dark:text-teal-300',
    'bg-amber-500/15 text-amber-700 dark:text-amber-300',
];
const avatarColor = (name) => avatarColors[[...name].reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % avatarColors.length];
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="py-6 sm:py-8">
            <div class="page max-w-[75rem] space-y-6">
                <!-- Greeting -->
                <section class="flex flex-wrap items-end justify-between gap-4">
                    <div class="space-y-1.5">
                        <p class="text-[13px] text-gray-500">{{ todayLabel }}</p>
                        <h1 class="text-[30px] leading-tight font-bold tracking-[-0.025em] text-gray-900">{{ greeting }}, {{ firstName }}</h1>
                        <p class="text-[15px] text-gray-500">{{ waitingLine }}</p>
                    </div>
                    <Deferred data="aiOnline">
                        <template #fallback>
                            <span class="inline-flex h-8 items-center gap-2 rounded-full border border-gray-200 px-3 text-xs text-gray-500">
                                <span class="size-[7px] rounded-full bg-gray-300" /> Checking assistant…
                            </span>
                        </template>
                        <span class="inline-flex h-8 items-center gap-2 rounded-full border border-gray-200 px-3 text-xs text-gray-500">
                            <span
                                class="size-[7px] rounded-full"
                                :class="aiOnline ? 'bg-accent shadow-[0_0_0_3px_rgb(var(--accent)/0.18)]' : 'bg-red-500'"
                            />
                            Assistant {{ aiOnline ? 'online' : 'offline' }}
                        </span>
                    </Deferred>
                </section>

                <!-- Stats -->
                <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <Link
                        v-for="stat in stats"
                        :key="stat.label"
                        :href="stat.href"
                        class="panel p-4 sm:p-5 flex flex-col gap-3 hover:border-gray-300 transition"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[13px] font-medium text-gray-500">{{ stat.label }}</span>
                            <span
                                class="hidden sm:flex size-[30px] shrink-0 items-center justify-center rounded-lg"
                                :class="stat.attention ? 'bg-amber-400/15 text-amber-600 dark:text-amber-300' : 'bg-gray-50 text-gray-500'"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="statIcons[stat.icon]" />
                                </svg>
                            </span>
                        </div>
                        <span
                            class="text-[28px] sm:text-[32px] leading-none font-bold tracking-[-0.03em] tabular-nums"
                            :class="stat.attention ? 'text-amber-600 dark:text-amber-300' : 'text-gray-900'"
                        >{{ stat.value }}</span>
                        <span v-if="stat.delta !== undefined" class="flex items-center gap-1 text-xs" :class="stat.delta > 0 ? 'text-indigo-600' : 'text-gray-500'">
                            <svg v-if="stat.delta !== 0" class="size-3.5 shrink-0" :class="{ 'rotate-180': stat.delta < 0 }" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0-6.75 6.75M12 4.5l6.75 6.75" />
                            </svg>
                            {{ stat.delta === 0 ? 'Same as last week' : `${stat.delta > 0 ? '+' : '−'}${Math.abs(stat.delta)} vs last week` }}
                        </span>
                        <span v-else class="text-xs truncate" :class="stat.good ? 'text-indigo-600' : 'text-gray-500'">{{ stat.note }}</span>
                    </Link>
                </section>

                <section class="flex flex-wrap items-start gap-6">
                    <!-- Follow-ups -->
                    <div class="panel overflow-hidden min-w-0 flex-[2_1_560px]">
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-[18px]">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Follow-ups</h2>
                                <p class="text-[13px] text-gray-500">Oldest first, so nobody goes cold</p>
                            </div>
                            <div v-if="dueContacts.length" role="tablist" aria-label="Follow-ups" class="flex gap-0.5 rounded-[10px] border border-gray-200 bg-gray-100 p-[3px]">
                                <button
                                    v-for="t in tabs"
                                    :key="t.key"
                                    type="button"
                                    role="tab"
                                    :aria-selected="tab === t.key"
                                    class="h-[30px] rounded-[7px] px-3 text-[13px] font-medium transition"
                                    :class="tab === t.key ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:text-gray-800'"
                                    @click="tab = t.key"
                                >
                                    {{ t.label }}
                                    <span v-if="t.key !== 'all'" :class="t.key === 'overdue' ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400'">{{ t.items.length }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="! dueContacts.length" class="border-t border-gray-200 px-5 py-10 text-center">
                            <p class="font-medium text-gray-700">You're all caught up.</p>
                            <p class="mt-1 text-sm text-gray-500">New contacts get a follow-up date two days after you meet.</p>
                        </div>
                        <template v-else>
                            <p v-if="! shownDue.length" class="border-t border-gray-200 px-5 py-8 text-center text-sm text-gray-500">Nobody here right now.</p>
                            <div
                                v-for="contact in shownDue"
                                :key="contact.id"
                                class="flex flex-wrap items-center gap-3.5 border-t border-gray-200 px-5 py-3.5 hover:bg-gray-50 transition"
                            >
                                <Link :href="route('contacts.show', contact.id)" class="flex min-w-0 flex-[1_1_160px] items-center gap-3.5">
                                    <span class="flex size-[38px] shrink-0 items-center justify-center rounded-full text-[13px] font-semibold" :class="avatarColor(contact.full_name)">{{ initials(contact.full_name) }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-gray-900">{{ contact.full_name }}</span>
                                        <span class="block truncate text-[13px] text-gray-400">{{ contact.company || 'No company added' }}</span>
                                    </span>
                                </Link>
                                <span
                                    class="inline-flex h-[26px] shrink-0 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium"
                                    :class="dueLabel(contact.next_follow_up_at).overdue ? 'bg-amber-400/15 text-amber-700 dark:text-amber-300' : 'bg-gray-50 text-gray-600'"
                                >
                                    <svg class="size-[13px]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="statIcons.clock" />
                                    </svg>
                                    {{ dueLabel(contact.next_follow_up_at).text }}
                                </span>
                                <Link :href="draftHref(contact)" class="btn-quiet h-9 shrink-0">
                                    <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                                    </svg>
                                    Draft email
                                </Link>
                                <FollowUpMenu :contact-id="contact.id" variant="icon" class="shrink-0" />
                            </div>
                            <Link
                                v-if="stats.due > dueContacts.length"
                                :href="route('contacts.index')"
                                class="flex h-12 items-center justify-center gap-1.5 border-t border-gray-200 text-[13px] font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                See all {{ stats.due }} follow-ups
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </Link>
                        </template>
                    </div>

                    <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-6">
                        <!-- Scan call to action -->
                        <section class="flex flex-col gap-3.5 rounded-[14px] border border-accent/25 bg-indigo-50 p-[22px]">
                            <p class="eyebrow !text-indigo-600">AI card scanner</p>
                            <div class="space-y-1.5">
                                <h2 class="text-lg font-semibold tracking-[-0.01em] text-gray-900">Met someone? Scan their card.</h2>
                                <p class="text-[13px] leading-relaxed text-gray-500">The AI reads it and flags anything it is unsure about. Nothing is saved until you confirm.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('scan.create')" class="btn-accent h-10 gap-2 px-4 py-0">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                    </svg>
                                    Scan a card
                                </Link>
                                <Link :href="route('contacts.create')" class="inline-flex h-10 items-center rounded-[10px] border border-accent/30 px-3.5 text-sm font-medium text-gray-800 hover:bg-accent/10 transition">Add by hand</Link>
                            </div>
                        </section>

                        <!-- Share your card -->
                        <section class="panel flex flex-col gap-[18px] p-[22px]">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="text-base font-semibold text-gray-900">Share your card</h2>
                                <!-- One compact switcher, however many cards there are. -->
                                <Dropdown v-if="shareCards.length > 1" align="right" width="72" :content-classes="['py-1', 'bg-white', 'max-h-72', 'overflow-y-auto']">
                                    <template #trigger>
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:border-gray-300 hover:text-gray-900 transition" aria-haspopup="listbox">
                                            Card {{ shareCardIndex + 1 }} of {{ shareCards.length }}
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <div role="listbox" aria-label="Card to share">
                                            <button
                                                v-for="card in shareCards"
                                                :key="card.id"
                                                type="button"
                                                role="option"
                                                :aria-selected="card.id === shareCard.id"
                                                class="flex w-full items-center gap-3 px-3 py-2 text-start hover:bg-gray-50 transition"
                                                @click="pickShareCard(card)"
                                            >
                                                <img v-if="card.photo_url" :src="card.photo_url" alt="" class="size-8 shrink-0 rounded-full object-cover">
                                                <span v-else class="size-8 shrink-0 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-semibold" aria-hidden="true">{{ initials(card.full_name) }}</span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-medium text-gray-900">{{ shareLabels[card.id] }}</span>
                                                    <span class="block truncate text-xs text-gray-500">{{ card.job_title || card.full_name }}</span>
                                                </span>
                                                <svg v-if="card.id === shareCard.id" class="size-4 shrink-0 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                                <span v-else-if="shareCard" class="inline-flex h-6 items-center gap-1.5 rounded-full bg-accent/10 px-2.5 text-xs font-medium text-indigo-600">
                                    <span class="size-1.5 rounded-full bg-accent" /> Public
                                </span>
                            </div>
                            <template v-if="shareCard">
                                <div class="flex items-center gap-4">
                                    <div class="shrink-0 rounded-xl bg-[#fff] p-1.5">
                                        <QrCode :value="shareCard.public_url" :size="100" />
                                    </div>
                                    <div class="min-w-0 space-y-1">
                                        <img v-if="shareCard.photo_url" :src="shareCard.photo_url" alt="" class="mb-1 size-10 rounded-full object-cover border border-gray-200">
                                        <p class="text-[15px] font-semibold text-gray-900">{{ shareCard.full_name }}</p>
                                        <p class="text-[13px] leading-snug text-gray-500">{{ [shareCard.job_title, shareCard.company].filter(Boolean).join(' · ') }}</p>
                                        <p class="text-xs text-gray-400">Let them scan it with their phone camera.</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="btn-quiet h-[38px] flex-1" @click="copyLink">
                                        <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                                        Copy link
                                    </button>
                                    <a :href="shareCard.public_url" target="_blank" rel="noopener" class="btn-quiet h-[38px] flex-1">
                                        Open page
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" /></svg>
                                    </a>
                                </div>
                            </template>
                            <div v-else-if="stats.cards > 0" class="flex flex-col items-center justify-center gap-3 py-6 text-center">
                                <p class="text-sm text-gray-500">All your cards are hidden, so there's no QR code to share.</p>
                                <Link :href="route('cards.index')" class="btn-accent">Go to My Cards</Link>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center gap-3 py-6 text-center">
                                <p class="text-sm text-gray-500">Create a card to get your link and QR code.</p>
                                <Link :href="route('cards.create')" class="btn-accent">Create your card</Link>
                            </div>
                        </section>
                    </div>
                </section>

                <!-- Recently added -->
                <section class="panel overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-[18px]">
                        <h2 class="text-base font-semibold text-gray-900">Recently added</h2>
                        <Link :href="route('contacts.index')" class="text-[13px] font-medium text-indigo-600 hover:text-indigo-700">View all contacts</Link>
                    </div>
                    <p v-if="! recentContacts.length" class="border-t border-gray-200 px-5 py-10 text-center text-sm text-gray-500">
                        No contacts yet. Scan a card or share your QR code at your next meeting.
                    </p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-sm">
                            <thead>
                                <tr class="text-start text-xs text-gray-400">
                                    <th class="border-t border-gray-200 px-5 py-2.5 text-start font-medium">Name</th>
                                    <th class="border-t border-gray-200 px-5 py-2.5 text-start font-medium">How they came in</th>
                                    <th class="border-t border-gray-200 px-5 py-2.5 text-start font-medium">Card</th>
                                    <th class="border-t border-gray-200 px-5 py-2.5 text-end font-medium">Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="contact in recentContacts" :key="contact.id" class="hover:bg-gray-50 transition">
                                    <td class="border-t border-gray-200 px-5 py-3">
                                        <Link :href="route('contacts.show', contact.id)" class="flex items-center gap-3">
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold" :class="avatarColor(contact.full_name)">{{ initials(contact.full_name) }}</span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold text-gray-900">{{ contact.full_name }}</span>
                                                <span class="block truncate text-xs text-gray-400">{{ contact.company || 'No company added' }}</span>
                                            </span>
                                        </Link>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3">
                                        <span class="inline-flex items-center gap-2 text-gray-600">
                                            <span class="size-[7px] rounded-sm" :class="sources[contact.source]?.dot ?? 'bg-gray-400'" />
                                            {{ sources[contact.source]?.label ?? contact.source }}
                                        </span>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3" :class="contact.card ? 'text-gray-600' : 'text-gray-400'">{{ contact.card ?? 'No card' }}</td>
                                    <td class="border-t border-gray-200 px-5 py-3 text-end text-gray-500 tabular-nums">{{ addedLabel(contact.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
