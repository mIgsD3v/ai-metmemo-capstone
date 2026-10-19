<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    as: String,
});

const classes = computed(() => [
    'flex w-full items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition duration-150 ease-in-out focus:outline-none',
    props.active
        ? 'bg-indigo-50 text-indigo-600'
        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 focus:bg-gray-50',
]);
</script>

<template>
    <button v-if="as == 'button'" :class="classes" class="text-start">
        <slot />
    </button>

    <Link v-else :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
