<script setup>
import LegalPage from '@/Components/LegalPage.vue';

defineProps({
    terms: String,
});

const highlights = [
    { title: 'Your content is yours', text: 'Cards, contacts and notes belong to you.' },
    { title: 'The AI only drafts', text: 'It never sends messages or edits contacts by itself.' },
    { title: 'Use it fairly', text: 'No spam, bulk messaging or misuse of other people\'s details.' },
];
</script>

<template>
    <LegalPage
        title="Terms of Service"
        current="terms"
        :html="terms"
        summary="The rules for using MetMemo, in plain language. By creating an account, you agree to them."
        :highlights="highlights"
    />
</template>
