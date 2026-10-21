<script setup>
import { computed } from 'vue';

/**
 * Ranked horizontal bars for one series (e.g. leads by event). Labels are text, the value sits at
 * each bar's tip, and hovering or focusing a row adds its share of the total. Bars are 10px thick,
 * square at the baseline with a 4px rounded data-end.
 */
const props = defineProps({
    // [{ label: 'PhilConstruct 2026', value: 14, muted: false }]
    rows: { type: Array, required: true },
    color: { type: String, default: 'rgb(var(--accent))' },
    unit: { type: String, default: 'contacts' },
});

const max = computed(() => Math.max(1, ...props.rows.map((row) => row.value)));
const total = computed(() => props.rows.reduce((sum, row) => sum + row.value, 0));
const share = (value) => (total.value ? Math.round((value / total.value) * 100) : 0);
</script>

<template>
    <ul class="space-y-1">
        <li
            v-for="row in rows"
            :key="row.label"
            tabindex="0"
            class="group rounded-lg px-2 py-1.5 -mx-2 outline-none hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500"
            :aria-label="`${row.label}: ${row.value} ${unit}, ${share(row.value)}%`"
        >
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="truncate" :class="row.muted ? 'text-gray-500' : 'text-gray-700'" :title="row.label">{{ row.label }}</span>
                <span class="shrink-0 tabular-nums">
                    <span class="me-1.5 text-xs text-gray-500 opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">{{ share(row.value) }}%</span>
                    <span class="font-semibold text-gray-900">{{ row.value }}</span>
                </span>
            </div>
            <div class="mt-1.5 h-2.5" aria-hidden="true">
                <div
                    v-if="row.value > 0"
                    class="h-full rounded-e transition-[width] duration-300"
                    :style="{ width: `${Math.max(1.5, (row.value / max) * 100)}%`, background: row.muted ? 'var(--status-neutral)' : color }"
                />
            </div>
        </li>
    </ul>
</template>
