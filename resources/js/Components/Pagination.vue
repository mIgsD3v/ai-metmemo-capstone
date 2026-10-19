<script setup>
import { computed } from 'vue';

// Footer for a Laravel paginator: "Showing 1 to 10 of 500 records", page buttons, and page size.
const props = defineProps({
    paginator: { type: Object, required: true },
    perPageOptions: { type: Array, default: () => [10, 25, 50, 100] },
    label: { type: String, default: 'records' },
});

const emit = defineEmits(['change']);

const current = computed(() => props.paginator.current_page);
const last = computed(() => props.paginator.last_page);

// First two, last two, and the pages around the current one; gaps become "…".
const pages = computed(() => {
    const keep = new Set([1, 2, last.value - 1, last.value, current.value - 1, current.value, current.value + 1]);
    if (current.value <= 3) [3, 4].forEach((page) => keep.add(page));
    if (current.value >= last.value - 2) [last.value - 2, last.value - 3].forEach((page) => keep.add(page));

    const sorted = [...keep].filter((page) => page >= 1 && page <= last.value).sort((a, b) => a - b);
    const items = [];
    sorted.forEach((page, index) => {
        const previous = sorted[index - 1];
        if (previous && page - previous === 2) items.push(previous + 1);
        else if (previous && page - previous > 2) items.push('…');
        items.push(page);
    });
    return items;
});

const go = (page) => {
    if (page < 1 || page > last.value || page === current.value) return;
    emit('change', { page, perPage: props.paginator.per_page });
};

const setPerPage = (event) => emit('change', { page: 1, perPage: Number(event.target.value) });

const navButton = 'inline-flex items-center justify-center gap-1 h-9 min-w-9 px-2 rounded-md text-sm font-medium transition disabled:opacity-40 disabled:pointer-events-none text-gray-600 hover:bg-gray-100 hover:text-gray-900';
</script>

<template>
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-sm">
        <!-- Summary and page size (phones: one row) -->
        <div class="flex items-center justify-between gap-3 lg:contents">
            <p class="text-gray-500 lg:order-1 tabular-nums">
                <template v-if="paginator.total">
                    Showing <span class="font-medium text-gray-900">{{ paginator.from }}</span>
                    to <span class="font-medium text-gray-900">{{ paginator.to }}</span>
                    of <span class="font-medium text-gray-900">{{ paginator.total }}</span> {{ label }}
                </template>
                <template v-else>No {{ label }}</template>
            </p>

            <label class="flex items-center gap-2 lg:order-3 text-gray-500">
                <span class="hidden sm:inline whitespace-nowrap">Records per page:</span>
                <span class="sm:hidden whitespace-nowrap">Per page</span>
                <select
                    :value="paginator.per_page"
                    class="rounded-lg border-gray-300 py-1.5 ps-3 pe-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    aria-label="Records per page"
                    @change="setPerPage"
                >
                    <option v-for="size in perPageOptions" :key="size" :value="size">{{ size }}</option>
                </select>
            </label>
        </div>

        <!-- Page buttons -->
        <nav v-if="last > 1" class="flex items-center justify-center gap-1 lg:order-2" aria-label="Pages">
            <button type="button" :class="navButton" :disabled="current === 1" aria-label="First page" @click="go(1)">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" /></svg>
            </button>
            <button type="button" :class="navButton" :disabled="current === 1" aria-label="Previous page" @click="go(current - 1)">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                <span class="hidden sm:inline">Previous</span>
            </button>

            <!-- Phones: compact -->
            <span class="sm:hidden px-2 text-gray-600 tabular-nums">Page <b class="text-gray-900">{{ current }}</b> of {{ last }}</span>

            <!-- Larger screens: numbered -->
            <template v-for="(item, index) in pages" :key="`${item}-${index}`">
                <span v-if="item === '…'" class="hidden sm:inline-flex h-9 min-w-9 items-center justify-center text-gray-400" aria-hidden="true">…</span>
                <button
                    v-else
                    type="button"
                    class="hidden sm:inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm font-medium tabular-nums transition"
                    :class="item === current ? 'bg-accent text-accent-fg' : 'text-gray-700 hover:bg-gray-100'"
                    :aria-current="item === current ? 'page' : undefined"
                    :aria-label="`Page ${item}`"
                    @click="go(item)"
                >
                    {{ item }}
                </button>
            </template>

            <button type="button" :class="navButton" :disabled="current === last" aria-label="Next page" @click="go(current + 1)">
                <span class="hidden sm:inline">Next</span>
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
            <button type="button" :class="navButton" :disabled="current === last" aria-label="Last page" @click="go(last)">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </nav>
    </div>
</template>
