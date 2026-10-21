<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BarList from '@/Components/Charts/BarList.vue';
import StackedColumns from '@/Components/Charts/StackedColumns.vue';

const props = defineProps({
    insights: Object,
    ranges: Array,
});

const rangeLabel = (weeks) => (weeks === 26 ? '6 months' : `${weeks} weeks`);

// The range scopes every number on the page. While it reloads, the page keeps its frame (dimmed).
const loading = ref(false);
const setRange = (weeks) => {
    if (weeks === props.insights.range.weeks) return;
    router.get(route('insights'), { weeks: weeks === props.ranges[1] ? undefined : weeks }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
    });
};

// Dates arrive as YYYY-MM-DD; read them as local days.
const day = (value, options = { month: 'short', day: 'numeric' }) => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString(undefined, options);
};
const period = computed(() => `${day(props.insights.range.from)} – ${day(props.insights.range.to, { month: 'short', day: 'numeric', year: 'numeric' })}`);

// --- Stat tiles -------------------------------------------------------------------------------
const delta = (now, before) => {
    if (now === before) return { text: 'Same as the previous period', tone: 'flat' };
    const diff = now - before;
    return { text: `${diff > 0 ? '+' : '−'}${Math.abs(diff)} vs previous ${rangeLabel(props.insights.range.weeks)}`, tone: diff > 0 ? 'up' : 'down' };
};

const followUps = computed(() => props.insights.followUps);
const followedRate = computed(() => (followUps.value.met ? Math.round((followUps.value.followedUp / followUps.value.met) * 100) : null));

const tiles = computed(() => [
    { label: 'New contacts', value: props.insights.totals.contacts, delta: delta(props.insights.totals.contacts, props.insights.totals.contactsBefore) },
    { label: 'Leads from your cards', value: props.insights.totals.fromCards, delta: delta(props.insights.totals.fromCards, props.insights.totals.fromCardsBefore) },
    {
        label: 'Followed up',
        value: followedRate.value === null ? '—' : `${followedRate.value}%`,
        note: followUps.value.met ? `${followUps.value.followedUp} of ${followUps.value.met} people you met` : 'No one met in this period',
    },
    {
        label: 'Days to first follow-up',
        value: followUps.value.averageDays ?? '—',
        note: followUps.value.averageDays === null ? 'Mark contacts as followed up to see this' : 'Average, from the day you met',
    },
]);

// --- New contacts per week ----------------------------------------------------------------------
// Fixed order (bottom to top), so a colour always means the same source.
const sources = [
    { key: 'scan', label: 'Scanned', color: 'var(--series-1)' },
    { key: 'lead_form', label: 'Card form', color: 'var(--series-2)' },
    { key: 'manual', label: 'Added by hand', color: 'var(--series-3)' },
];
const weeklyRows = computed(() => props.insights.weekly.map((week) => ({
    label: day(week.week),
    title: `Week of ${day(week.week)}`,
    values: { scan: week.scan, lead_form: week.lead_form, manual: week.manual },
})));
const sourceTotals = computed(() => Object.fromEntries(sources.map((s) => [s.key, props.insights.weekly.reduce((sum, w) => sum + w[s.key], 0)])));
const weeklyTable = ref(false);

// --- Events & cards -----------------------------------------------------------------------------
const eventRows = computed(() => [
    ...props.insights.events.top.map((event) => ({ label: event.label, value: event.total })),
    ...(props.insights.events.other ? [{ label: `Other events (${props.insights.events.otherCount})`, value: props.insights.events.other, muted: true }] : []),
]);
const cardRows = computed(() => props.insights.cards.map((card) => ({ label: card.public ? card.label : `${card.label} (hidden)`, value: card.total })));

// --- Follow-up health ---------------------------------------------------------------------------
// Status colours are reserved for this, and always shown with an icon and a label.
const healthStates = [
    { key: 'overdue', label: 'Overdue', color: 'var(--status-critical)', icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z' },
    { key: 'today', label: 'Due today', color: 'var(--status-warning)', icon: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
    { key: 'upcoming', label: 'Scheduled', color: 'var(--status-good)', icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
    { key: 'none', label: 'No follow-up date', color: 'var(--status-neutral)', icon: 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
];
const healthTotal = computed(() => healthStates.reduce((sum, s) => sum + followUps.value.health[s.key], 0));
const healthShare = (key) => (healthTotal.value ? Math.round((followUps.value.health[key] / healthTotal.value) * 100) : 0);
</script>

<template>
    <AppLayout title="Insights">
        <template #header>
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Insights</h2>
                <p class="text-sm text-gray-500 mt-1">{{ period }}</p>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- One filter row, above everything it scopes. -->
                <div class="inline-flex rounded-md border border-gray-300 bg-white p-1 shadow-sm" role="radiogroup" aria-label="Period">
                    <button
                        v-for="weeks in ranges"
                        :key="weeks"
                        type="button"
                        role="radio"
                        :aria-checked="insights.range.weeks === weeks"
                        class="rounded px-3 sm:px-4 py-1.5 text-sm font-medium transition"
                        :class="insights.range.weeks === weeks ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-900'"
                        @click="setRange(weeks)"
                    >
                        Last {{ rangeLabel(weeks) }}
                    </button>
                </div>

                <div class="space-y-6 transition-opacity" :class="{ 'opacity-50': loading }">
                    <!-- Stat tiles -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                        <div v-for="tile in tiles" :key="tile.label" class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5">
                            <p class="text-sm text-gray-500">{{ tile.label }}</p>
                            <p class="mt-1 text-2xl sm:text-3xl font-semibold text-gray-900">{{ tile.value }}</p>
                            <p v-if="tile.delta" class="mt-1 flex items-center gap-1 text-xs" :class="tile.delta.tone === 'up' ? 'text-indigo-600' : 'text-gray-500'">
                                <svg v-if="tile.delta.tone !== 'flat'" class="size-3.5 shrink-0" :class="{ 'rotate-180': tile.delta.tone === 'down' }" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0-6.75 6.75M12 4.5l6.75 6.75" />
                                </svg>
                                {{ tile.delta.text }}
                            </p>
                            <p v-else class="mt-1 text-xs text-gray-500">{{ tile.note }}</p>
                        </div>
                    </div>

                    <!-- New contacts per week -->
                    <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">New contacts per week</h3>
                                <p class="text-sm text-gray-500 mt-1">Is your networking growing, and which way are people coming in?</p>
                            </div>
                            <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition" :aria-pressed="weeklyTable" @click="weeklyTable = ! weeklyTable">
                                {{ weeklyTable ? 'Show chart' : 'Show table' }}
                            </button>
                        </div>

                        <!-- Legend: always present for 2+ series, with each source's total. -->
                        <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                            <li v-for="s in sources" :key="s.key" class="flex items-center gap-2">
                                <span class="size-2.5 rounded-sm" :style="{ background: s.color }" aria-hidden="true" />
                                <span class="text-gray-700">{{ s.label }}</span>
                                <span class="font-semibold text-gray-900 tabular-nums">{{ sourceTotals[s.key] }}</span>
                            </li>
                        </ul>

                        <div v-if="! weeklyTable" class="mt-4">
                            <StackedColumns v-if="insights.totals.contacts" :rows="weeklyRows" :series="sources" />
                            <p v-else class="py-16 text-center text-sm text-gray-500">No new contacts in this period yet.</p>
                        </div>
                        <div v-else class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">Week of</th>
                                        <th v-for="s in sources" :key="s.key" class="py-2 px-4 font-medium text-right">{{ s.label }}</th>
                                        <th class="py-2 ps-4 font-medium text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 tabular-nums">
                                    <tr v-for="row in weeklyRows" :key="row.title">
                                        <td class="py-2 pe-4 text-gray-700">{{ row.label }}</td>
                                        <td v-for="s in sources" :key="s.key" class="py-2 px-4 text-right text-gray-700">{{ row.values[s.key] }}</td>
                                        <td class="py-2 ps-4 text-right font-semibold text-gray-900">{{ sources.reduce((sum, s) => sum + row.values[s.key], 0) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Leads by event -->
                        <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                            <h3 class="font-semibold text-gray-900">Contacts by event</h3>
                            <p class="text-sm text-gray-500 mt-1">Which events are worth going back to?</p>
                            <div class="mt-4">
                                <BarList v-if="eventRows.length" :rows="eventRows" />
                                <p v-else class="py-10 text-center text-sm text-gray-500">
                                    No events recorded in this period. Fill in <span class="font-medium text-gray-700">Where you met</span> when you add someone.
                                </p>
                            </div>
                        </section>

                        <!-- Leads per card -->
                        <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                            <h3 class="font-semibold text-gray-900">Contacts per card</h3>
                            <p class="text-sm text-gray-500 mt-1">Which of your cards actually brings people in?</p>
                            <div class="mt-4">
                                <BarList v-if="cardRows.length" :rows="cardRows" />
                                <div v-else class="py-10 text-center text-sm text-gray-500 space-y-3">
                                    <p>You don't have a card yet.</p>
                                    <Link :href="route('cards.create')" class="btn-accent">Create your card</Link>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Follow-up health -->
                    <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">Follow-up health</h3>
                                <p class="text-sm text-gray-500 mt-1">All {{ healthTotal }} contacts, right now. Are leads going cold?</p>
                            </div>
                            <Link v-if="followUps.health.overdue" :href="route('dashboard')" class="text-sm text-indigo-600 hover:underline">Catch up on overdue →</Link>
                        </div>

                        <!-- Part-to-whole bar with 2px surface gaps; the list below carries every value. -->
                        <div v-if="healthTotal" class="mt-5 flex h-3 gap-0.5 overflow-hidden rounded" aria-hidden="true">
                            <div
                                v-for="state in healthStates.filter((s) => followUps.health[s.key])"
                                :key="state.key"
                                class="h-full first:rounded-s last:rounded-e"
                                :style="{ flexGrow: followUps.health[state.key], background: state.color }"
                            />
                        </div>

                        <ul class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <li v-for="state in healthStates" :key="state.key" class="rounded-xl border border-gray-200 p-3">
                                <div class="flex items-center gap-2 text-sm text-gray-700">
                                    <svg class="size-4 shrink-0" :style="{ color: state.color }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="state.icon" />
                                    </svg>
                                    {{ state.label }}
                                </div>
                                <p class="mt-1 text-xl font-semibold text-gray-900">
                                    {{ followUps.health[state.key] }}
                                    <span class="text-xs font-normal text-gray-500">{{ healthShare(state.key) }}%</span>
                                </p>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
