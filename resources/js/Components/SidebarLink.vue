<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    as: String,
    label: String,
    // Icon only: the label becomes a hover tooltip (and stays readable to screen readers).
    collapsed: Boolean,
    // A count beside the label. "warning" (amber pill) for things waiting on you, otherwise a quiet number.
    badge: Number,
    badgeTone: String,
});

const classes = computed(() => [
    'group relative flex w-full items-center gap-3 rounded-[9px] h-10 text-sm font-medium transition duration-150 ease-in-out focus:outline-none',
    props.collapsed ? 'justify-center px-0' : 'px-3',
    props.active
        ? 'bg-accent/10 text-indigo-600'
        : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50 focus:bg-gray-50',
]);
</script>

<template>
    <component
        :is="as == 'button' ? 'button' : Link"
        :href="as == 'button' ? undefined : href"
        :class="[classes, { 'text-start': as == 'button' }]"
        :aria-current="active ? 'page' : undefined"
        :aria-label="collapsed ? label : undefined"
    >
        <slot />
        <span v-if="! collapsed" class="flex-1 truncate">{{ label }}</span>
        <template v-if="! collapsed && badge">
            <span
                v-if="badgeTone === 'warning'"
                class="flex h-5 min-w-[22px] items-center justify-center rounded-full bg-amber-400/15 px-1.5 text-[11px] font-semibold text-amber-600 dark:text-amber-300"
            >{{ badge }}</span>
            <span v-else class="text-xs text-gray-400 tabular-nums">{{ badge }}</span>
        </template>
        <span
            v-if="collapsed && badge && badgeTone === 'warning'"
            class="absolute end-1.5 top-1.5 size-2 rounded-full bg-amber-400"
            aria-hidden="true"
        />
        <span
            v-if="collapsed"
            class="pointer-events-none absolute start-full ms-3 whitespace-nowrap rounded-md bg-gray-900 dark:bg-gray-100 px-2 py-1 text-xs font-medium text-white dark:text-gray-900 opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-visible:opacity-100 z-50"
            aria-hidden="true"
        >
            {{ label }}
        </span>
    </component>
</template>
