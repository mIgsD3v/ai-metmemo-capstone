<script setup>
import { router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';

// "I reached out today": records it and picks when to follow up next (or never).
const props = defineProps({
    contactId: { type: String, required: true },
    // "button": a labelled button (contact page). "icon": a compact check (dashboard rows).
    variant: { type: String, default: 'button' },
});

const choices = [
    { days: 3, label: 'In 3 days' },
    { days: 7, label: 'In 1 week' },
    { days: 14, label: 'In 2 weeks' },
    { days: 30, label: 'In 1 month' },
    { days: null, label: 'No more follow-ups' },
];

const check = 'm4.5 12.75 6 6 9-13.5';

const markFollowedUp = (days) => {
    router.post(route('contacts.followed-up', props.contactId), { next_in_days: days }, { preserveScroll: true });
};
</script>

<template>
    <Dropdown :align="variant === 'icon' ? 'right' : 'left'" width="48">
        <template #trigger>
            <button
                v-if="variant === 'icon'"
                type="button"
                class="flex size-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-indigo-500 hover:text-indigo-600 transition"
                aria-label="Mark as followed up"
                title="Mark as followed up"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="check" />
                </svg>
            </button>
            <button
                v-else
                type="button"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="check" />
                </svg>
                Mark as followed up
            </button>
        </template>

        <template #content>
            <p class="px-4 pt-2 pb-1 text-xs font-medium text-gray-500">Next follow-up</p>
            <button
                v-for="choice in choices"
                :key="choice.label"
                type="button"
                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition"
                @click="markFollowedUp(choice.days)"
            >
                {{ choice.label }}
            </button>
        </template>
    </Dropdown>
</template>
