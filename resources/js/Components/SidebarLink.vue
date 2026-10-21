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
});

const classes = computed(() => [
    'group relative flex w-full items-center gap-3 rounded-lg py-2 text-sm font-medium transition duration-150 ease-in-out focus:outline-none',
    props.collapsed ? 'justify-center px-0' : 'px-3',
    props.active
        ? 'bg-indigo-50 text-indigo-600'
        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 focus:bg-gray-50',
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
        <span v-if="! collapsed" class="truncate">{{ label }}</span>
        <span
            v-else
            class="pointer-events-none absolute start-full ms-3 whitespace-nowrap rounded-md bg-gray-900 dark:bg-gray-100 px-2 py-1 text-xs font-medium text-white dark:text-gray-900 opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-visible:opacity-100 z-50"
            aria-hidden="true"
        >
            {{ label }}
        </span>
    </component>
</template>
