<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * Stacked columns (e.g. new contacts per week, split by source). Follows the chart rules:
 * thin columns (<= 24px), 2px surface gap between segments, 4px rounded top, hairline grid,
 * one tooltip per column listing every series, and the column's whole band as the hover target.
 */
const props = defineProps({
    // [{ label: 'Oct 6', title: 'Week of Oct 6', values: { scan: 2, ... } }]
    rows: { type: Array, required: true },
    // [{ key: 'scan', label: 'Scanned', color: 'var(--series-1)' }], bottom to top, fixed order.
    series: { type: Array, required: true },
    height: { type: Number, default: 240 },
});

const root = ref(null);
const width = ref(600);
let observer;

onMounted(() => {
    observer = new ResizeObserver(([entry]) => { width.value = Math.max(240, entry.contentRect.width); });
    observer.observe(root.value);
});
onUnmounted(() => observer?.disconnect());

const pad = { top: 8, right: 4, bottom: 28, left: 32 };
const plotWidth = computed(() => width.value - pad.left - pad.right);
const plotHeight = computed(() => props.height - pad.top - pad.bottom);

const totals = computed(() => props.rows.map((row) => props.series.reduce((sum, s) => sum + (row.values[s.key] ?? 0), 0)));

// Clean y-axis ticks: 1, 2 or 5 times a power of ten, about four steps.
const ticks = computed(() => {
    const max = Math.max(1, ...totals.value);
    const raw = max / 4;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const step = Math.max(1, [1, 2, 5, 10].map((m) => m * magnitude).find((s) => s >= raw));
    const top = Math.ceil(max / step) * step;
    return Array.from({ length: top / step + 1 }, (_, i) => i * step);
});
const yMax = computed(() => ticks.value[ticks.value.length - 1]);
const y = (value) => pad.top + plotHeight.value - (value / yMax.value) * plotHeight.value;

const band = computed(() => plotWidth.value / Math.max(1, props.rows.length));
const barWidth = computed(() => Math.min(24, band.value * 0.6));
const center = (i) => pad.left + band.value * i + band.value / 2;

// Show every nth x label so they never collide (about 48px each).
const labelEvery = computed(() => Math.ceil(props.rows.length / Math.max(1, Math.floor(plotWidth.value / 48))));

const GAP = 2;
const RADIUS = 4;

/** Path for a segment; only the top one gets the 4px rounded data-end, the baseline stays square. */
const segmentPath = (x, top, w, h, rounded) => {
    if (! rounded || h < RADIUS) return `M${x},${top + h}V${top}H${x + w}V${top + h}Z`;
    const r = RADIUS;
    return `M${x},${top + h}V${top + r}Q${x},${top} ${x + r},${top}H${x + w - r}Q${x + w},${top} ${x + w},${top + r}V${top + h}Z`;
};

const columns = computed(() => props.rows.map((row, i) => {
    const x = center(i) - barWidth.value / 2;
    const present = props.series.filter((s) => (row.values[s.key] ?? 0) > 0);
    let running = 0;

    const segments = present.map((s, index) => {
        const value = row.values[s.key];
        const bottom = y(running);
        running += value;
        const isTop = index === present.length - 1;
        // The surface gap is taken from the top of every segment that has another one above it.
        const top = y(running) + (isTop ? 0 : GAP);
        return { key: s.key, color: s.color, d: segmentPath(x, top, barWidth.value, Math.max(0, bottom - top), isTop) };
    });

    return { i, row, segments, total: totals.value[i] };
}));

const hovered = ref(null);
const tooltipLeft = computed(() => {
    if (hovered.value === null) return 0;
    // Keep the 11rem tooltip inside the chart.
    return Math.min(Math.max(center(hovered.value), 90), width.value - 90);
});
</script>

<template>
    <div ref="root" class="relative" :style="{ height: `${height}px` }" @pointerleave="hovered = null">
        <svg :width="width" :height="height" class="block overflow-visible" role="img" :aria-label="`Stacked columns: ${series.map((s) => s.label).join(', ')}`">
            <!-- Hairline grid and y ticks: recessive, solid, never dashed. -->
            <g v-for="tick in ticks" :key="tick">
                <line :x1="pad.left" :x2="width - pad.right" :y1="y(tick)" :y2="y(tick)" :class="tick === 0 ? 'stroke-gray-300' : 'stroke-gray-200'" stroke-width="1" shape-rendering="crispEdges" />
                <text :x="pad.left - 8" :y="y(tick)" text-anchor="end" dominant-baseline="middle" class="fill-gray-500 text-[11px] tabular-nums">{{ tick }}</text>
            </g>

            <g v-for="column in columns" :key="column.i" :opacity="hovered === null || hovered === column.i ? 1 : 0.45" class="transition-opacity">
                <path v-for="segment in column.segments" :key="segment.key" :d="segment.d" :style="{ fill: segment.color }" />
            </g>

            <text
                v-for="column in columns.filter((c) => c.i % labelEvery === 0)"
                :key="`x-${column.i}`"
                :x="center(column.i)"
                :y="height - 8"
                text-anchor="middle"
                class="fill-gray-500 text-[11px]"
            >{{ column.row.label }}</text>

            <!-- Each column's whole band is its hover/focus target, bigger than the mark. -->
            <rect
                v-for="column in columns"
                :key="`hit-${column.i}`"
                :x="pad.left + band * column.i"
                :y="pad.top"
                :width="band"
                :height="plotHeight"
                fill="transparent"
                tabindex="0"
                class="outline-none cursor-default"
                :aria-label="`${column.row.title}: ${column.total} total`"
                @pointerenter="hovered = column.i"
                @focus="hovered = column.i"
                @blur="hovered = null"
            />
        </svg>

        <!-- One tooltip, every series. Values lead (strong), names follow; keyed with a short line. -->
        <div
            v-if="hovered !== null"
            class="pointer-events-none absolute top-0 z-10 w-44 -translate-x-1/2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs shadow-lg"
            :style="{ left: `${tooltipLeft}px` }"
        >
            <p class="font-medium text-gray-900">{{ rows[hovered].title }}</p>
            <ul class="mt-1.5 space-y-1">
                <li v-for="s in [...series].reverse()" :key="s.key" class="flex items-center gap-2">
                    <span class="h-0.5 w-3 rounded-full" :style="{ background: s.color }" aria-hidden="true" />
                    <span class="font-semibold text-gray-900 tabular-nums">{{ rows[hovered].values[s.key] ?? 0 }}</span>
                    <span class="text-gray-500">{{ s.label }}</span>
                </li>
            </ul>
            <p class="mt-1.5 border-t border-gray-200 pt-1.5 text-gray-500"><span class="font-semibold text-gray-900 tabular-nums">{{ totals[hovered] }}</span> total</p>
        </div>
    </div>
</template>
