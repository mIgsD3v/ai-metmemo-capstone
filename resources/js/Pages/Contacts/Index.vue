<script setup>
import { today as localToday } from '@/dates';
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    contacts: Object,
    cards: Array,
    filters: Object,
    perPageOptions: Array,
});

const search = ref(props.filters.search ?? '');
const card = ref(props.filters.card ?? '');
const due = ref(props.filters.due ?? '');

// Changing search, card or status starts again at page 1; the page size is kept.
const reload = ({ page, perPage } = {}) => {
    router.get(route('contacts.index'), {
        search: search.value || undefined,
        card: card.value || undefined,
        due: due.value || undefined,
        per_page: (perPage ?? props.filters.per_page) === props.perPageOptions[0] ? undefined : (perPage ?? props.filters.per_page),
        page: page > 1 ? page : undefined,
    }, { preserveState: true, preserveScroll: ! page, replace: ! page });
};

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => reload(), 300);
});
watch(card, () => reload());
watch(due, () => reload());

const statusTabs = [
    { value: '', label: 'All' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'week', label: 'This week' },
    { value: 'none', label: 'No reminder' },
];

const activeCard = computed(() => props.cards.find((option) => option.id === props.filters.card));
const heading = computed(() => {
    if (props.filters.card === 'none') return 'Not from a card';
    return activeCard.value ? `Contacts from ${activeCard.value.full_name}` : 'Contacts';
});

const today = localToday();

const toDate = (value) => {
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
};
const formatDay = (value) => value ? toDate(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';
const daysLate = (value) => Math.round((toDate(today) - toDate(value)) / 86_400_000);

// One reading of the follow-up date, shared by the table and the phone list.
const followUp = (contact) => {
    const value = contact.next_follow_up_at;
    if (! value) return { kind: 'none', text: 'No reminder', short: 'No reminder' };
    const late = daysLate(value);
    if (late > 0) return { kind: 'late', text: `${formatDay(value)} · ${late}d late`, short: `${late}d late` };
    if (late === 0) return { kind: 'soon', text: 'Today', short: 'Today' };
    if (late === -1) return { kind: 'soon', text: 'Tomorrow', short: 'Tomorrow' };
    return { kind: 'later', text: formatDay(value), short: formatDay(value) };
};

const overdueHere = computed(() => props.contacts.data.filter((contact) => followUp(contact).kind === 'late').length);
const summary = computed(() => {
    const people = `${props.contacts.total} ${props.contacts.total === 1 ? 'person' : 'people'}`;
    return overdueHere.value ? `${people} · ${overdueHere.value} overdue on this page` : people;
});

// Phones: this page's contacts grouped by what to do about them.
const groups = computed(() => [
    { key: 'late', label: 'Overdue', tone: 'text-amber-600 dark:text-amber-300', items: props.contacts.data.filter((c) => followUp(c).kind === 'late') },
    { key: 'next', label: 'Coming up', tone: 'text-gray-400', items: props.contacts.data.filter((c) => ['soon', 'later'].includes(followUp(c).kind)) },
    { key: 'none', label: 'No reminder', tone: 'text-gray-400', items: props.contacts.data.filter((c) => followUp(c).kind === 'none') },
].filter((group) => group.items.length));

const sources = {
    manual: { label: 'Added by hand', dot: 'bg-accent' },
    lead_form: { label: 'Card form', dot: 'bg-orange-500' },
    scan: { label: 'Scanned', dot: 'bg-blue-500' },
    swap: { label: 'Card swap', dot: 'bg-violet-500' },
};

// Exports use the filters the list is showing (not text still being typed), so you get what you see.
const exportOptions = [
    { format: 'xlsx', label: 'Excel (.xlsx)', hint: 'For Excel or Google Sheets' },
    { format: 'csv', label: 'CSV (.csv)', hint: 'For any spreadsheet or CRM import' },
    { format: 'vcf', label: 'vCard (.vcf)', hint: 'Import into your phone or Google Contacts' },
];
const filtered = computed(() => Boolean(props.filters.search || props.filters.card));
const exportUrl = (format) => route('contacts.export', {
    format,
    search: props.filters.search || undefined,
    card: props.filters.card || undefined,
});

const initials = (name) => name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase();

// Each person keeps the same avatar colour everywhere: picked from their name (same as the dashboard).
const avatarColors = [
    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    'bg-violet-500/15 text-violet-700 dark:text-violet-300',
    'bg-teal-500/15 text-teal-700 dark:text-teal-300',
    'bg-amber-500/15 text-amber-700 dark:text-amber-300',
];
const avatarColor = (name) => avatarColors[[...name].reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % avatarColors.length];

const exportIcon = 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3';
</script>

<template>
    <AppLayout title="Contacts">
        <div class="py-4 sm:py-8">
            <div class="page max-w-[75rem] space-y-4 sm:space-y-5">
                <!-- Heading and actions -->
                <section class="flex flex-wrap items-end justify-between gap-3 sm:gap-4">
                    <div class="min-w-0 space-y-0.5 sm:space-y-1.5">
                        <h1 class="truncate text-xl sm:text-[30px] leading-tight font-bold tracking-[-0.025em] text-gray-900">{{ heading }}</h1>
                        <p class="text-xs sm:text-[15px] text-gray-500">{{ summary }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 sm:gap-2.5">
                        <Dropdown v-if="contacts.total" align="right" width="72">
                            <template #trigger>
                                <button type="button" class="btn-quiet size-11 sm:size-auto sm:h-10 sm:px-3.5 text-sm" aria-haspopup="menu" aria-label="Export contacts">
                                    <svg class="size-[18px] sm:size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="exportIcon" />
                                    </svg>
                                    <span class="hidden sm:inline">Export</span>
                                </button>
                            </template>
                            <template #content>
                                <p class="px-4 pt-2 pb-1 text-xs text-gray-500">
                                    {{ filtered ? `The ${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'} matching your search and card` : `All ${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'}` }}
                                </p>
                                <a
                                    v-for="option in exportOptions"
                                    :key="option.format"
                                    :href="exportUrl(option.format)"
                                    class="block px-4 py-2 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition"
                                >
                                    <span class="block text-sm font-medium text-gray-900">{{ option.label }}</span>
                                    <span class="block text-xs text-gray-500">{{ option.hint }}</span>
                                </a>
                            </template>
                        </Dropdown>
                        <Link :href="route('contacts.create')" class="btn-accent size-11 sm:size-auto sm:h-10 gap-2 px-0 sm:px-4 py-0" aria-label="New contact">
                            <svg class="size-5 sm:size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span class="hidden sm:inline">New contact</span>
                        </Link>
                    </div>
                </section>

                <!-- Filters: search, follow-up status, card -->
                <section class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2.5">
                    <label class="flex h-11 sm:h-10 shrink-0 sm:shrink sm:flex-[1_1_260px] items-center gap-2.5 rounded-xl sm:rounded-[10px] border border-gray-200 bg-white px-3.5 sm:px-3 text-gray-400 sm:max-w-[360px] focus-within:border-accent/60">
                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Name, company, email or event"
                            aria-label="Search contacts"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </label>

                    <!-- Desktop: one segmented control. Phones: scrolling chips. -->
                    <div role="tablist" aria-label="Follow-up status" class="hidden sm:flex gap-0.5 rounded-[10px] border border-gray-200 bg-white p-[3px]">
                        <button
                            v-for="tab in statusTabs"
                            :key="tab.value"
                            type="button"
                            role="tab"
                            :aria-selected="due === tab.value"
                            class="h-8 rounded-[7px] px-3 text-[13px] font-medium transition"
                            :class="due === tab.value ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:text-gray-800'"
                            @click="due = tab.value"
                        >{{ tab.label }}</button>
                    </div>
                    <div role="tablist" aria-label="Follow-up status" class="-mx-4 flex gap-2 overflow-x-auto px-4 sm:hidden">
                        <button
                            v-for="tab in statusTabs"
                            :key="tab.value"
                            type="button"
                            role="tab"
                            :aria-selected="due === tab.value"
                            class="h-9 shrink-0 rounded-full border px-[13px] text-[13px] font-medium transition"
                            :class="due === tab.value ? 'border-gray-800 bg-gray-800 text-page' : 'border-gray-300 bg-gray-50 text-gray-600'"
                            @click="due = tab.value"
                        >{{ tab.label }}</button>
                    </div>

                    <select
                        v-model="card"
                        class="h-11 sm:h-10 w-full sm:w-auto sm:max-w-[16rem] rounded-xl sm:rounded-[10px] border-gray-200 bg-white text-[13px] font-medium text-gray-600 focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Filter by card"
                    >
                        <option value="">Card: All</option>
                        <option v-for="option in cards" :key="option.id" :value="option.id">
                            Card: {{ option.full_name }}{{ option.job_title ? ` · ${option.job_title}` : '' }}
                        </option>
                        <option value="none">Not from a card</option>
                    </select>
                </section>

                <!-- Phones: grouped list -->
                <div class="-mx-4 sm:hidden">
                    <p v-if="! contacts.data.length" class="px-4 py-10 text-center text-sm text-gray-500">No contacts found.</p>
                    <section v-for="group in groups" :key="group.key">
                        <h2 class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-[0.06em]" :class="group.tone">
                            {{ group.label }}<template v-if="group.key === 'late'"> · {{ group.items.length }}</template>
                        </h2>
                        <Link
                            v-for="contact in group.items"
                            :key="contact.id"
                            :href="route('contacts.show', contact.id)"
                            class="flex min-h-16 items-center gap-3 px-4 py-2.5 transition active:bg-gray-50"
                        >
                            <span class="flex size-[42px] shrink-0 items-center justify-center rounded-full text-sm font-semibold" :class="avatarColor(contact.full_name)">{{ initials(contact.full_name) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[15px] font-semibold text-gray-900">{{ contact.full_name }}</span>
                                <span class="block truncate text-[13px] text-gray-500">
                                    {{ [contact.company, contact.event].filter(Boolean).join(' · ') || sources[contact.source]?.label }}
                                </span>
                            </span>
                            <span
                                v-if="followUp(contact).kind === 'late'"
                                class="flex h-6 shrink-0 items-center rounded-full bg-amber-400/15 px-[9px] text-xs font-semibold text-amber-700 dark:text-amber-300"
                            >{{ followUp(contact).short }}</span>
                            <span v-else-if="followUp(contact).kind === 'none'" class="shrink-0 text-[13px] font-medium text-indigo-600">+ Set</span>
                            <span v-else class="shrink-0 text-[13px]" :class="followUp(contact).kind === 'soon' ? 'font-medium text-gray-900' : 'text-gray-500'">{{ followUp(contact).short }}</span>
                        </Link>
                    </section>
                </div>

                <!-- Larger screens: table -->
                <div class="panel hidden overflow-hidden sm:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-100 text-start text-xs text-gray-400">
                                    <th class="px-5 py-3 text-start font-medium">Name</th>
                                    <th class="px-5 py-3 text-start font-medium">Company</th>
                                    <th class="px-5 py-3 text-start font-medium">Card</th>
                                    <th class="px-5 py-3 text-start font-medium">Where you met</th>
                                    <th class="px-5 py-3 text-start font-medium">Source</th>
                                    <th class="px-5 py-3 text-start font-medium">Follow up</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-gray-50 transition">
                                    <td class="border-t border-gray-200 px-5 py-3">
                                        <Link :href="route('contacts.show', contact.id)" class="flex items-center gap-3 font-semibold text-gray-900 hover:text-indigo-600">
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold" :class="avatarColor(contact.full_name)">{{ initials(contact.full_name) }}</span>
                                            {{ contact.full_name }}
                                        </Link>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3 text-gray-600">{{ contact.company || '—' }}</td>
                                    <td class="border-t border-gray-200 px-5 py-3">
                                        <span v-if="contact.card" class="inline-flex h-6 items-center rounded-md border border-gray-300 bg-gray-50 px-[9px] text-xs text-gray-600">{{ contact.card.full_name }}</span>
                                        <span v-else class="text-gray-400">No card</span>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3" :class="contact.event ? 'text-gray-600' : 'text-gray-400'">{{ contact.event || '—' }}</td>
                                    <td class="border-t border-gray-200 px-5 py-3">
                                        <span class="inline-flex items-center gap-[7px] text-gray-600">
                                            <span class="size-[7px] rounded-sm" :class="sources[contact.source]?.dot ?? 'bg-gray-400'" />
                                            {{ sources[contact.source]?.label ?? contact.source }}
                                        </span>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3 tabular-nums">
                                        <span
                                            v-if="followUp(contact).kind === 'late'"
                                            class="inline-flex h-6 items-center rounded-full bg-amber-400/15 px-[9px] text-xs font-medium text-amber-700 dark:text-amber-300"
                                        >{{ followUp(contact).text }}</span>
                                        <span v-else :class="{ 'font-medium text-gray-900': followUp(contact).kind === 'soon', 'text-gray-600': followUp(contact).kind === 'later', 'text-gray-400': followUp(contact).kind === 'none' }">
                                            {{ followUp(contact).kind === 'none' ? '—' : followUp(contact).text }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="! contacts.data.length">
                                    <td colspan="6" class="border-t border-gray-200 px-5 py-10 text-center text-gray-500">No contacts found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="contacts.total" class="panel px-4 py-3 sm:px-5">
                    <Pagination :paginator="contacts" :per-page-options="perPageOptions" label="contacts" @change="reload" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
