<script setup>
import { computed } from 'vue';

// A contact's photo (from a card swap), else their initials in a colour picked from their name, so the same
// person looks the same everywhere. Size and text size come from the class you pass.
const props = defineProps({
    name: { type: String, required: true },
    photo: String,
});

const colors = [
    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    'bg-violet-500/15 text-violet-700 dark:text-violet-300',
    'bg-teal-500/15 text-teal-700 dark:text-teal-300',
    'bg-amber-500/15 text-amber-700 dark:text-amber-300',
];

const initials = computed(() => props.name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase());
const color = computed(() => colors[[...props.name].reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % colors.length]);
</script>

<template>
    <img v-if="photo" :src="photo" alt="" class="shrink-0 rounded-full object-cover">
    <span v-else class="flex shrink-0 items-center justify-center rounded-full font-semibold" :class="color" aria-hidden="true">{{ initials }}</span>
</template>
