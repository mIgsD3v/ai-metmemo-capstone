<script setup>
import { useSlots, watch } from 'vue';
import { toast } from '@/toast';

// Jetstream forms show "Saved." through this component; it now raises a toast instead of inline text.
const props = defineProps({
    on: Boolean,
});

const slots = useSlots();

const text = () => (slots.default?.() ?? [])
    .map((node) => (typeof node.children === 'string' ? node.children : ''))
    .join('')
    .trim();

watch(() => props.on, (on) => {
    if (on) toast(text() || 'Saved.');
});
</script>

<template>
    <span class="hidden" />
</template>
