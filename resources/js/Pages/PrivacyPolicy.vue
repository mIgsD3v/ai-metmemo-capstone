<script setup>
import LegalPage from '@/Components/LegalPage.vue';

defineProps({
    policy: String,
});

const highlights = [
    { title: 'Never sold', text: 'No ads, no data brokers, no sharing with other users.' },
    { title: 'Only your own data', text: 'Every account sees only its own cards, contacts and notes.' },
    { title: 'Masked for the AI', text: 'Phone numbers and emails are hidden before the assistant reads notes.' },
];
</script>

<template>
    <LegalPage
        title="Privacy Policy"
        current="policy"
        :html="policy"
        summary="What MetMemo stores, why it needs it, and which services help run it. In short: we keep only what the app needs to work for you."
        :highlights="highlights"
    />
</template>
